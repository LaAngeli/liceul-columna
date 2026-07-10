# Raport testare live — DISCIPLINE (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test`. Secțiune de
> CONSULTARE pentru profesor (nomenclatorul se administrează de configuratori). Fără
> artefacte de test.

## ✅ Ce funcționează corect (verificat)

- **Nomenclator complet vizibil** (38 discipline), cu coloane utile: Denumire, Abreviere,
  Mod notare (Notă numerică / Calificativ / Descriptiv — badge-uri), De la clasa / Până la
  clasa (treapta la care se predă).
- Gate-urile `ManagedByConfigurators` acoperă toate operațiile de scriere (doar
  `canConfigureSchool`) — protecția pe server există (tiparul verificat la Elevi: create →
  403).
- Dublurile aparente („Educație fizică" ×2, „Educație muzicală" ×2) sunt de fapt SPLIT-uri pe
  cicluri cu mod de notare diferit (Calificativ 5–12 vs Descriptiv 1–4) — design corect
  pentru §2.4.

## 🔴 De corectat

### 1. MEDIU (sistemic) — „Adăugare disciplină" + „Editare" pe fiecare rând = butoane moarte
- Toate cele 38 de rânduri afișează „Editare" pentru profesor; clickul duce la 403. Identic cu
  finding-ul sistemic din raport-elevi-staff.md #1 — fix-ul recomandat e același (Policies).
  Aici e cel mai vizibil (o listă întreagă de acțiuni inutilizabile).

## 🟡 Observații de date

- **„Educație ecologică — Notă numerică — de la clasa 12 până la 12"** — interval suspect
  (12–12) și mod numeric pentru o disciplină de tip opțional/transversal; de verificat cu
  școala dacă nu trebuie „Descriptiv" și alt interval.

## 💡 De îmbunătățit (UX)

- Filtru pe „Mod notare" (numeric/calificativ/descriptiv) — util administrației la configurare.
- Pentru profesor, un indicator pe disciplinele PROPRII (predate) ar face lista mai utilă.
