# Raport testare live — SETĂRI → NOTIFICĂRI (staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test`. Preferințele au fost
> modificate, salvate, verificate în DB și **restaurate la starea inițială**.

## ✅ Ce funcționează corect (verificat)

- **Limba notificărilor** separată de limba interfeței, cu explicație („Limba în care îți vor
  fi EXPEDIATE notificările (independent de limba interfeței)") — decizia din memoria
  `notification-system` (șabloane pe limbă), implementată vizibil.
- **Datele de contact** (e-mail / Telegram / Viber) partajate cu Profilul, cu propagare
  automată — anunțat explicit în ambele pagini.
- **Canalele neactivate de liceu sunt DEZACTIVATE vizual** (checkbox gri) cu eticheta „Liceul
  nu a activat încă acest canal" lângă Telegram și Viber — utilizatorul nu poate alege un canal
  care nu funcționează. Excelent (evită „am bifat și nu primesc nimic").
- **Matricea tip × canal per rol**: profesorul vede doar tipurile care-l privesc — „Mesaj nou"
  și „Anunț al conducerii" (nu notă/absență/temă, care sunt pentru familie). Fiecare tip are
  „Selectează toate" / „Deselectează toate" (comută corect eticheta).
- **Salvarea funcționează și persistă corect** (verificat în DB):
  bifat E-mail la „Mesaj nou" → `{"new_message": ["cabinet","email"], "announcement":
  ["cabinet"]}`; debifat → înapoi la `["cabinet"]`.
- Fără erori de consolă.

## 🔴 De corectat

Niciun bug funcțional observat.

## 🟡 Observații / de decis

- **Nu există canal „cabinet" pentru staff, ci clopoțelul Filament** — eticheta spune „Cabinet
  (în aplicație)", ceea ce pentru un profesor e derutant (el nu are cabinet, ci panou).
  **Fix**: etichetă contextuală („În panou" pentru staff, „În cabinet" pentru familie).
- **Nicio confirmare vizuală la salvare** (sau nu a fost surprinsă): butonul „Salvează
  preferințele" nu a produs un toast vizibil în captură. De verificat/adăugat notificare de
  succes.
- Tipul „Anunț al conducerii" apare deși dispatch-ul de anunțuri broadcast e încă marcat ca
  follow-up în documentația proiectului — de confirmat că preferința nu rămâne „moartă".

## 💡 De îmbunătățit (UX)

- Buton „Trimite-mi o notificare de test" (pe canalele active) — verificarea cea mai cerută de
  utilizatori după configurare.
- Un rezumat sus („Primești notificări pe: Cabinet, E-mail") ca utilizatorul să nu recitească
  matricea.
- Când toate canalele unui tip sunt debifate, un avertisment discret („Nu vei fi anunțat despre
  mesaje noi").
