# Backup & Restaurare — Liceul Columna (producție)

Procedura de restaurare, **testată pe 2026-07-15** (nu e teorie: dump decriptat → importat într-o bază
de test → toate cele 66 de tabele și toate rândurile au coincis cu producția).

---

## ⚠️ Trei lucruri de știut ÎNAINTE de incident

**1. Folosește `7z`, NU `unzip`.**
Arhivele sunt criptate **WinZip AES-256**. `unzip` (Info-ZIP) **nu suportă AES** și eșuează cu
`need PK compat. v5.1 (can do v4.6)` — arată exact ca o arhivă coruptă. **Nu e coruptă.** `p7zip-full`
e deja instalat pe VPS.

**2. Parola arhivei e OBLIGATORIE și trebuie să existe ÎN AFARA VPS-ului.**
- Pe server: `/root/.columna-backup-password` (chmod 600) și `BACKUP_ARCHIVE_PASSWORD` în `.env`.
- 🔴 **Dacă moare VPS-ul și parola exista doar acolo, backup-urile criptate sunt IRECUPERABILE. Definitiv.**
  Ține-o într-un manager de parole. La fel: `APP_KEY` (fără el, coloanele criptate — secretele 2FA —
  devin ilizibile) și parola bazei (`/root/.columna-db.env`).

**3. `.env` NU e în arhivă** (deliberat — n-are ce căuta acolo). La o restaurare completă îl recreezi;
valorile critice sunt în cele două fișiere root-only de mai sus.

---

## Ce se salvează, când, unde

| Ce | Când | Mărime | Comandă |
|---|---|---|---|
| **Baza de date** (note, absențe, mesaje, cereri **și textele site-ului**) | **zilnic 01:30** | ~1,6 MB | `backup:run --only-db` |
| **Fișiere** (bibliotecă, galerii, imagini, PDF-uri) | **duminică 02:30** | ~427 MB | `backup:run --only-files` |
| Curățare după retenție | zilnic 01:00 | — | `backup:clean` |
| Verificare sănătate (vechime + mărime) | zilnic 03:00 | — | `backup:monitor` |

**Destinație:** `storage/app/private/Laravel/` — 🔴 **pe același VPS**. Plafon 30 GB.
**Retenție:** 7 zile toate → 16 zile zilnice → 8 săptămâni → 4 luni → 2 ani.

> 🔴 **Lipsește copia EXTERNĂ.** Disc mort / cont pierdut ⇒ pierzi datele ȘI backup-urile. Hostinger are
> un backup propriu de VPS (săptămânal), dar tot în ecosistemul lor. **Prioritatea #1 rămasă.**

---

## A. Restaurare SELECTIVĂ (doar un tabel — ex. notele)

Cazul real: cineva a șters/stricat notele marți, ne-am prins joi. Nu vrei să pierzi tot ce s-a scris
între timp — vrei **doar** tabelul `grades` de marți.

`spatie/laravel-backup` **nu are comandă de restore**. Tiparul care funcționează (și e testat):
**restaurează într-o bază TEMPORARĂ, apoi copiază doar ce-ți trebuie.**

```bash
cd /var/www/columna
ZIP=storage/app/private/Laravel/<ARHIVA-DIN-ZIUA-BUNĂ>.zip
PASS=$(grep '^BACKUP_ARCHIVE_PASSWORD=' .env | cut -d= -f2-)
TMP=$(mktemp -d)

# 1) Decriptează + extrage (7z, NU unzip!)
7z x -p"$PASS" -o"$TMP" "$ZIP"

# 2) Importă în bază TEMPORARĂ (producția rămâne intactă)
mysql -e "CREATE DATABASE columna_tmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql columna_tmp < "$TMP/db-dumps/mysql-liceul_columna.sql"

# 3) Compară ÎNTÂI, ca să știi ce repari
mysql -e "SELECT (SELECT COUNT(*) FROM liceul_columna.grades) AS acum,
                 (SELECT COUNT(*) FROM columna_tmp.grades)   AS in_backup;"

# 4) Copiază DOAR tabelul dorit (ex. notele lipsă)
#    ⚠️ BACKUP AL PRODUCȚIEI ÎNAINTE: mysqldump liceul_columna > /root/pre-restore-$(date +%F).sql
mysql liceul_columna -e "
  INSERT INTO grades SELECT * FROM columna_tmp.grades g
  WHERE NOT EXISTS (SELECT 1 FROM liceul_columna.grades x WHERE x.id = g.id);"

# 5) Curățenie
mysql -e "DROP DATABASE columna_tmp;"
rm -rf "$TMP"
```

Același tipar merge pentru `absences`, `document_requests`, `messages`, `grade_corrections` etc.
**Atenție la FK-uri:** dacă restaurezi un tabel-copil, tabelul-părinte trebuie să conțină deja rândurile
referite (ex. `grades` are nevoie de `students`/`subjects` existente).

---

## B. Restaurare COMPLETĂ a bazei (dezastru)

```bash
cd /var/www/columna
php artisan down                                   # oprește traficul
mysqldump liceul_columna > /root/pre-restore-$(date +%F-%H%M).sql   # plasă: starea de ACUM
PASS=$(grep '^BACKUP_ARCHIVE_PASSWORD=' .env | cut -d= -f2-)
TMP=$(mktemp -d)
7z x -p"$PASS" -o"$TMP" storage/app/private/Laravel/<ARHIVA>.zip

mysql -e "DROP DATABASE liceul_columna; CREATE DATABASE liceul_columna CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql liceul_columna < "$TMP/db-dumps/mysql-liceul_columna.sql"

php artisan optimize:clear && php artisan config:cache && php artisan queue:restart
php artisan up
rm -rf "$TMP"
```

**Verifică după:** numărul de tabele (66) + câteva numărători (`users`, `grades`, `absences`) față de ce
te aștepți. Exact asta face testul de validare.

---

## C. Restaurare FIȘIERE (media pierdută)

```bash
PASS=$(grep '^BACKUP_ARCHIVE_PASSWORD=' /var/www/columna/.env | cut -d= -f2-)
7z x -p"$PASS" -o/tmp/restore storage/app/private/Laravel/<ARHIVA--only-files>.zip
# arhiva păstrează calea absolută: var/www/columna/...
rsync -a /tmp/restore/var/www/columna/storage/app/public/ /var/www/columna/storage/app/public/
rsync -a /tmp/restore/var/www/columna/public/images/      /var/www/columna/public/images/
chown -R www-data:www-data /var/www/columna/storage
rm -rf /tmp/restore
```

---

## D. Plasa de siguranță la DEPLOY (migrări)

MySQL **nu are DDL tranzacțional** → o migrare poate rămâne **aplicată pe jumătate**. Înainte de orice
`migrate --force`:

```bash
mysqldump liceul_columna | gzip > /root/pre-deploy-$(date +%F-%H%M).sql.gz
```

Costă 2 secunde și 1,6 MB. Dacă migrarea strică ceva, restaurezi exact starea de dinainte.

**Snapshot Hostinger** (buton în hPanel) acoperă mai mult — TOT serverul, nu doar baza. E **manual**;
se poate automatiza doar cu token API Hostinger. Fă-l înainte de schimbări mari (pachete, config nginx).
⚠️ Restaurarea unui snapshot derulează **tot** înapoi, inclusiv datele scrise între timp.

---

## E. Cum se re-testează (o dată pe semestru)

Restaurează într-o bază de test și compară numărătorile — **fără să atingi producția**:

```bash
7z x -p"$PASS" -o/tmp/t storage/app/private/Laravel/<ULTIMA>.zip
mysql -e "CREATE DATABASE restore_test CHARACTER SET utf8mb4;"
mysql restore_test < /tmp/t/db-dumps/mysql-liceul_columna.sql
for t in users students grades absences academic_records posts; do
  echo "$t: prod=$(mysql -N liceul_columna -e "SELECT COUNT(*) FROM $t;") restore=$(mysql -N restore_test -e "SELECT COUNT(*) FROM $t;")"
done
mysql -e "DROP DATABASE restore_test;"; rm -rf /tmp/t
```

Toate trebuie să coincidă. Dacă nu → backup-ul e stricat și **află acum, nu în ziua incidentului**.

---

## Ce lipsește încă (conștient)

| Lipsă | Risc |
|---|---|
| 🔴 **Copie EXTERNĂ** (rclone → Drive instituțional / S3-B2) | Pierderea VPS-ului = pierderea backup-urilor |
| 🟠 **Email real** pentru alerte (`BACKUP_NOTIFICATION_EMAIL` + SMTP) | Un backup eșuat trece în tăcere |
| 🟠 Parola arhivei + `APP_KEY` într-un **manager de parole** | Backup criptat fără parolă = gunoi |
