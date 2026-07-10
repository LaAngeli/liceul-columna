# Raport testare live — SETĂRI → PROFIL (staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test`. **Nu am executat**
> acțiunile distructive/sensibile ale contului (dezactivare 2FA, regenerare coduri de
> recuperare, afișarea codurilor) — sunt acțiuni ale utilizatorului, iar codurile sunt
> secrete. Verificat: structura, stările, textele, gărzile vizibile.

## ✅ Ce funcționează corect (verificat)

- **Secțiunea „General"**: numele e READ-ONLY cu explicație corectă („Numele e gestionat de
  administrație — dacă e greșit, cere corectarea (se modifică din «Utilizatori», cu urmă în
  audit)"). Exact tratamentul potrivit pentru un câmp de identitate.
- **„Date de contact"** (e-mail / Telegram / Viber) cu nota că sunt **aceleași** ca la
  Setări → Notificări și se propagă automat — sursă unică, fără dublare.
- **„Setare parolă"**: două câmpuri (nouă + confirmare) cu toggle de vizibilitate, gol =
  neschimbată.
- **„Autentificare în doi pași (2FA)"**: starea curentă e afișată clar („**Activă** — la
  logare ți se cere codul din aplicația de autentificare"), cu acțiunile Coduri de recuperare
  / Regenerează codurile / Dezactivează 2FA (roșu), plus alternativa „Cod pe e-mail: inactiv"
  cu buton de activare.
- **Ghidul cu QR-uri pentru aplicațiile de autentificare** (adăugat la cererea din această
  sesiune) apare la ÎNROLARE — corect că nu se mai afișează când 2FA e deja activă.
- Layout-ul (carduri secționate, lățime desktop) e cel corectat anterior — se citește bine pe
  desktop și pe mobil.

## 🔴 De corectat

Niciun bug funcțional observat în această trecere.

## 🟡 Observații / de decis

- **Re-autentificarea la acțiunile 2FA EXISTĂ** (verificat în cod, nu doar în UI): modalele
  de dezactivare 2FA, regenerare coduri, afișare coduri și activare cod-pe-e-mail cer toate
  `current_password` cu regula `current_password` — tipar „sudo mode" implementat corect.
  (Nu am executat modalele ca să nu ating contul; sursa e neechivocă.)
- „Regenerează codurile" — de confirmat prin test automat că setul vechi devine invalid imediat
  și că afișarea se face o singură dată (nu la fiecare vizită).
- Contactul Telegram/Viber apare aici fără eticheta „Liceul nu a activat încă acest canal"
  (care există în pagina Notificări) → utilizatorul poate crede că funcționează. De adăugat
  aceeași notă sau de ascuns câmpurile până la activarea canalelor.

## 💡 De îmbunătățit (UX)

- Un indicator „Ultima schimbare a parolei: …" + „Ultima autentificare: …" ar da context
  personalului (și e material bun pentru audit L133).
- Buton „Deconectează celelalte sesiuni" (Fortify/Laravel îl oferă) — util după pierderea unui
  telefon.
