# Raport testare live — CALENDAR (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Pagina instituțională de calendar (agregator read-only + evenimente manuale). Artefactul de
> test ([TEST UI] pe 11.07) a fost creat prin secțiunea Evenimente, văzut aici, apoi curățat.

## ✅ Ce funcționează corect (verificat)

- **Vederi**: Lună (implicită) / Săptămână / Zi / Agendă — comutare instantă; Agenda listează
  pe zile cu ora evenimentului; „Azi" + navigare ◀ ▶ pe luni.
- **Categorii-filtru** (chips): Teme / Evaluări și examene / Absențe / Termene-limită /
  Evenimente și ședințe / Orar / Structură / Comunicări — toggle funcțional (chip-ul se
  stinge, apare „Afișează tot" pentru reset), legendă jos.
- **Ziua curentă evidențiată** (10 iul., inel verde-brand).
- **Evenimentele manuale apar imediat**: evenimentul de test creat pentru XI R pe 11.07 a
  apărut în grila lunară fără nicio intervenție (agregatorul citește la zi).
- Evenimentele `[DEMO]` din seeder se afișează corect pe zilele lor, cu culoarea categoriei.

## 🟡 Observații de date / curățare

- **Evenimente de test istorice NEmarcate** rămase în calendar: „**Dup test**" (18 iul.) și
  „**test**" (30.06, autor contul demo profesor — vizibil în secțiunea Evenimente). De șters
  manual sau la curățarea `[DEMO]` (nu au prefixul standard, deci scapă de `--remove`).

## 💡 De îmbunătățit (UX)

- Click pe un eveniment în grilă nu deschide (încă) un detaliu/popover — pentru evenimentele
  manuale, un popover cu descriere + link de editare (dacă ai drept) ar închide bucla.
- Chip-urile de categorie nu persistă între vizite (se resetează la reload) — de memorat în
  sesiune/localStorage, cum face sidebar-ul.
- În vederea Lună, evenimentele lungi (multi-zi) apar doar pe ziua de start (de verificat cu
  un eveniment cu „Se termină" setat — nu s-a testat de data asta).
