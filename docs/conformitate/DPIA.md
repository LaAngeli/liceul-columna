# Evaluarea impactului asupra protecției datelor (DPIA)
## Platforma „Liceul Columna" — catalog electronic și cabinet personal

> **ȘABLON DE COMPLETAT.** Câmpurile marcate cu «...» se completează de operator / responsabilul cu
> protecția datelor (DPO). Document obligatoriu **înainte de punerea în funcțiune** (Legea 133/2011;
> spec §7). Măsurile tehnice descrise la §10 sunt deja implementate în platformă.

---

## 0. Informații generale

| Câmp | Valoare |
|---|---|
| Operator de date | IPL „Liceul Columna", «adresă», «IDNO» |
| Responsabil protecția datelor (DPO) | «nume, contact» |
| Autoritatea de supraveghere | Centrul Național pentru Protecția Datelor cu Caracter Personal (CNPDCP) |
| Înregistrare la CNPDCP | «nr. / data, dacă există» |
| Autor DPIA | «nume» |
| Data | «zz.ll.aaaa» · Versiune: 1.0 (draft) |
| Data următoarei revizuiri | «zz.ll.aaaa» (recomandat: anual sau la orice schimbare majoră) |

---

## 1. Descrierea sistematică a prelucrării

**Scopul prelucrării:** ținerea catalogului școlar electronic (note, absențe, medii, situație
academică), comunicarea cu familiile și gestiunea administrativă a procesului de învățământ.

**Natura prelucrării:** colectare, înregistrare, stocare, consultare, utilizare, modificare (cu
istoric), ștergere la expirarea retenției. Prelucrare automatizată (aplicație web) + acces uman scoped.

**Context:** platformă internă a unui liceu privat; date introduse de personalul didactic/administrativ,
consultate de familii (elev/părinte) și personal, conform rolurilor.

«Completați orice particularitate: integrări externe, AI, notificări prin rețele sociale etc.»

---

## 2. Categoriile de date și persoanele vizate

| Persoană vizată | Categorii de date |
|---|---|
| **Elevi (MINORI)** | nume, prenume, sex, an de naștere/treaptă, clasă, număr matricol, note, absențe, medii, situație (promovat/corigent/amânat), cereri tipice |
| **Părinți / reprezentanți legali** | nume, legătură cu elevul, date de contact (e-mail, eventual rețele sociale — opțional), cont de acces |
| **Personal** (profesori, diriginți, administrație) | nume, funcție, cont de acces, acțiuni în catalog (audit) |

**Categorii speciale de date:** «Confirmați dacă se prelucrează — în mod normal NU (fără date de
sănătate/biometrice). Justificativele de motivare a absențelor pot conține date medicale → tratați-le
ca date sensibile.»

---

## 3. Temeiul legal al prelucrării

«De confirmat de DPO.» Temeiuri posibile (Legea 133/2011, art. 5):
- obligație legală a instituției de învățământ (ținerea evidenței școlare);
- și/sau **consimțământul informat** al părintelui/reprezentantului legal pentru prelucrările care nu
  decurg strict din obligația legală (ex. contacte pe rețele sociale pentru notificări).

> ⚠️ Fluxul de **consimțământ** al părintelui este încă de implementat în platformă (amânat conștient).

---

## 4. Necesitate și proporționalitate (minimizarea datelor)

Se colectează **doar** datele necesare scopului școlar. Nu se colectează date irelevante. Datele
publice (orare la nivel de clasă) nu conțin PII de elev. «Confirmați că nu se colectează date în exces.»

---

## 5. Destinatari și transferuri

| Destinatar | Ce primește | Temei |
|---|---|---|
| Familia elevului (elev/părinte) | datele propriului copil | acces propriu |
| Personalul școlii | datele scoped pe rol (profesorul doar clasele lui etc.) | necesitate operațională |
| «Furnizori (hosting, e-mail, rețele sociale)» | «date minime» | «contract de prelucrare» |

**Transferuri în afara țării:** «Da/Nu — dacă se folosesc servicii externe (e-mail, Telegram/Viber/
Meta), documentați-le și temeiul transferului.»

---

## 6. Perioade de păstrare

- Arhivă școlară: **12 ani** de la încheierea parcursului școlar al elevului (justificat de durata
  studiilor și de eventualele verificări ulterioare).
- După expirare: **ștergere definitivă** (decizie a conducerii, 27.06.2026). Implementat tehnic prin
  comanda `app:purge-expired-students` (cascadă pe tot dosarul + fișiere PII). «Confirmați anchorul:
  data de plecare `left_on`.»

---

## 7. Drepturile persoanelor vizate

Operatorul asigură: dreptul de **acces** (cabinetul afișează datele; dosarul e exportabil PDF),
**rectificare** (prin secretariat/diriginte, cu istoric în audit), **ștergere** (la expirarea
retenției sau la cerere justificată), **opoziție/restricționare** «descrieți procedura de primire și
soluționare a cererilor — cine, în ce termen». Notele NU se șterg în timpul școlarizării (se anulează
cu motiv, păstrând istoricul — cerință de integritate a evidenței).

---

## 8. Evaluarea riscurilor

| Risc | Probabilitate | Impact | Măsuri de reducere (vezi §10) |
|---|---|---|---|
| Acces neautorizat la datele unui minor | «Mică/Medie» | Ridicat | RBAC + scoping pe rol, jurnalizarea accesului, autentificare |
| Scurgere/exfiltrare de date | «Mică» | Ridicat | criptare în tranzit, stocare privată a PDF-urilor, minimizare |
| Modificare neautorizată a notelor | «Mică» | Mediu | fără ștergere, corecții cu aprobare, audit complet |
| Păstrare excesivă | «Medie» | Mediu | retenție 12 ani + ștergere automatizabilă |
| «alt risc» | | | |

---

## 9. Măsuri tehnice și organizatorice (DEJA implementate)

- **Control acces pe roluri (RBAC)** + scoping fin (profesorul vede doar clasele lui; familia doar
  copilul ei) — pe server, nu din frontend.
- **Jurnal de audit** (cine ce a modificat **și vizualizat/exportat**) — neștergibil, vizibil conducerii
  (`/admin/audits`); administratorul tehnic nu vede accesul la date academice (minimizare).
- **Fără ștergere de note** — anulare cu motiv + corecții cu aprobare (integritatea evidenței).
- **Stocare privată** a documentelor PDF cu PII (nu pe URL public); descărcare doar autentificat.
- **Retenție** automatizabilă (ștergere după 12 ani).
- **Criptare în tranzit** (HTTPS). «Criptare la repaus a BD — de confirmat la nivel de infrastructură.»
- **Minimizarea datelor** + parolele stocate doar ca hash (bcrypt); parolele legacy în clar NU au fost
  migrate.

**Măsuri organizatorice de completat:** «instruirea personalului, politica de parole, procedura de
incident (vezi runbook-ul separat), contracte de prelucrare cu furnizorii.»

---

## 10. Risc rezidual și concluzie

«După aplicarea măsurilor, riscul rezidual este: Scăzut / Mediu / Ridicat.»

«Concluzie: prelucrarea poate / nu poate începe; condiții suplimentare.»

| Aviz | Nume | Data | Semnătură |
|---|---|---|---|
| Responsabil protecția datelor (DPO) | «...» | | |
| Conducerea instituției | «...» | | |

---

## 11. Plan de revizuire

DPIA se revizuiește «anual» și la orice modificare majoră (funcție nouă cu date personale, integrare AI,
schimbare de furnizor, incident de securitate).
