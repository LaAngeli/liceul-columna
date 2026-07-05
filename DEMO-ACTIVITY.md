# Activitate demo — „Monitor activitate" (curățare la deploy)

> Date de DEMONSTRAȚIE create pentru a testa vizual widget-ul **Monitor activitate** (`/admin`).
> **TREBUIE curățate înainte de go-live** (alături de restul datelor `[DEMO]`). Toată activitatea e
> reversibilă printr-o singură comandă.

## Comanda

```bash
php artisan app:demo-activity                 # creează activitate densă (cont implicit: Bujor-Cobili Carolina)
php artisan app:demo-activity --remove        # șterge tot ce a creat (curățare completă)
php artisan app:demo-activity --name="Alt Nume"   # țintește alt cont [DEMO]
```

- **Siguranță:** operează EXCLUSIV pe conturi al căror nume începe cu `[DEMO]`. Refuză conturile reale.
- Scrie prin query builder → **fără** observers / notificări / audit (nicio dată derivată, niciun spam).
- Idempotentă: refuză să creeze de două ori (cere `--remove` întâi).

## Ce s-a creat acum (2026-07-05)

Cont: **`[DEMO] Bujor-Cobili Carolina`** (`user_id=2`, profesor + diriginte), împrăștiat pe ultimele 6 luni:

| Serie (Monitor activitate) | Tabel | Rânduri | Atribuire |
|---|---|---|---|
| Note | `grades` | 133 | `teacher_id` = fișa ei |
| Absențe | `absences` | 79 | `teacher_id` = fișa ei |
| Corecții note | `grade_corrections` | 24 | `requested_by_user_id` = 2 |
| Motivări absențe | `absence_motivations` | 16 | `reviewed_by_user_id` = 2 |
| Mesaje | `messages` | 33 | `sender_user_id` = 2 |

## Cum se curăță (mecanism)

1. **Manifest** (autoritativ): la creare, ID-urile fiecărui rând se scriu în
   `storage/app/demo/activity-{userId}.json`. `--remove` citește manifestul și șterge EXACT acele
   rânduri, în ordine sigură FK (mesaje → motivări → corecții → absențe → note), apoi șterge manifestul.
   → Așa se curăță și notele/absențele, care **nu au câmp text** de marcat.
2. **Marcaj `[DEMO]`** (redundant, pentru corecții/motivări/mesaje): câmpurile text (`reason`,
   `review_note`, `subject`, `body`) sunt prefixate cu `[DEMO]`. Dacă manifestul se pierde,
   `--remove` cade pe un fallback care șterge aceste rânduri marcate după `user_id` (notele/absențele
   NU pot fi identificate fără manifest — de aceea manifestul e sursa principală).

## Evenimente de calendar demo (widgetul „Evenimente apropiate")

Separat de comanda de mai sus, pentru a demonstra widgetul **„Evenimente apropiate"** au fost inserate
**5 evenimente de calendar** cu titlul prefixat `[DEMO]` (ședință părinți, teză, concurs, consiliu
profesoral, zi porți deschise), împrăștiate pe următoarele ~3 săptămâni. Sunt globale (nu legate de un
cont), deci NU intră în manifestul de mai sus. Curățare (idempotent):

```bash
php artisan tinker --execute "App\Models\CalendarEvent::where('title','like','[DEMO]%')->forceDelete();"
```

## La deploy

- Rulează `php artisan app:demo-activity --remove` **înainte** de a promova baza spre producție, SAU
  pur și simplu nu rula comanda pe producție (producția pornește din `migrate --force`, fără aceste date).
- Șterge evenimentele de calendar `[DEMO]` (comanda tinker de mai sus).
- Vezi și curățarea generală `[DEMO]`: `php artisan app:demo-accounts --remove` (conturi) și
  `DemoTestDataSeeder` (corecții/motivări/mesaje demo marcate `[DEMO]`).
