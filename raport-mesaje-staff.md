# Raport testare live — MESAJE / Poșta internă (panou staff, rol Profesor)

> Secțiunea a fost RESCRISĂ de la zero în aceeași zi (10.07.2026, tipar Gmail) și verificată
> LIVE extensiv imediat după rescriere, în același browser/cont. Acest raport SINTETIZEAZĂ
> acea verificare (nu a fost re-rulată în această trecere, ca să nu dubleze artefactele de
> test din căsuțe reale).

## ✅ Verificat LIVE la livrare (aceeași sesiune)

- **Foldere e-mail** (Primite / Cu stea / Trimise / Arhivă / Coș + Audiențe pentru staff):
  semantică Gmail — firul inițiat de mine intră în Primite abia la primul răspuns; arhivarea
  scoate din Primite dar NU din Trimise; Coșul e exclusiv; steaua vede și arhivate.
- **Acțiuni per-user** (stea / arhivă / coș / marchează necitit): starea unui utilizator nu o
  afectează pe a celuilalt (verificat pe perechi de conturi, în DB `message_states`).
- **Deep-link-uri**: `?folder=`, `?fir=` (id de răspuns → deschide rădăcina firului), `?q=`
  (căutare pe server) — funcționale; fir străin prin URL → 403 (testat HTTP).
- **Compose în overlay** (card jos-dreapta, bară navy) cu agenda pe 4 categorii ABSOLUTE:
  Administrație / Colegi (cadre didactice) / Părinți / Elevi — ultimele două DOAR pentru
  elevii claselor predate (ancoră compusă elev↔cont). Super-adminul nu apare în agendă.
- **Reply inline** pe fir (nu modal) cu atașamente FilePond; fără pierdere tăcută de fișier:
  marcajul „încărcare în curs" blochează expedierea cu eroare sub câmp.
- **Sincronizare staff ↔ cabinet elev/părinte**: mesaj trimis din panou apare instant în
  cabinetul familiei și invers (aceeași sursă `MessageMailbox`); ambele UI-uri identice ca
  logică.
- **Atașamente**: stocare privată, descărcare doar prin rută autentificată de participant;
  whitelist de tipuri (fără svg/html), limite din config.
- Suita completă: 763/763 teste verzi la livrare (MailboxTest + StaffMailboxTest 17 teste +
  MessagingTest).

## 🔴 Rămase de corectat (din verificarea de livrare)

- Nimic blocant cunoscut la momentul acestei sinteze. Punctele deschise sunt de natură
  evolutivă (mai jos).

## 💡 Evoluții propuse

- **Poll pe listă doar** (30s) există; un indicator „mesaj nou" în timp real (websockets) e
  pasul următor natural — momentan necitirile apar la poll/navigare.
- Căutarea acoperă subiect/corp/expeditor/destinatar — de adăugat operatori simpli
  („de la:", „cu atașament") când va cere școala.
- Semnătură personalizabilă per utilizator (cerere probabilă de la secretariat).
