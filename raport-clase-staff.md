# Raport testare live — CLASE (panou staff, rol Profesor)

> Testat fizic în browser, 10.07.2026, cont demo `profesor@columna.test` (diriginte XI R).
> Secțiune de CONSULTARE pentru profesor. Fără artefacte de test.

## ✅ Ce funcționează corect (verificat)

- **Scoping-ul listei**: 11 clase = exact clasele vizibile ale profesorului (predare +
  dirigenție); XI R apare cu diriginta corectă (Bujor-Cobili Carolina — contul demo).
- Coloane clare: Clasa / Litera-secția / Treapta / An școlar / Diriginte.
- Gate-urile de scriere `ManagedByConfigurators` (doar `canConfigureSchool`) — protecție pe
  server (tipar verificat la Elevi: create → 403).

## 🔴 De corectat

### 1. MEDIU (sistemic) — „Adăugare clasă" + „Editare" pe rânduri = butoane moarte (403)
- Identic cu finding-ul sistemic din raport-elevi-staff.md #1; fix-ul recomandat: Policies
  per model care deleagă la capabilitățile existente (`canConfigureSchool()`).

## 🟡 Observații

- Numele claselor e pe stil vechi („VII 1", „X R/U") — consecvent cu restul aplicației.
- Anul școlar afișat 2025–2026 pe toate — corect pentru anul curent; la deschiderea anului
  nou (flux administrator operațional) lista va avea nevoie de filtrul pe an (există sortare,
  nu și filtru).

## 💡 De îmbunătățit (UX)

- Număr de elevi per clasă (coloană count) — informație frecvent căutată de diriginte.
- Link de pe rând către lista elevilor clasei (acum drumul e Elevi → căutare manuală).
