# Runbook — Răspuns la incidente de securitate a datelor
## Platforma „Liceul Columna"

> **ȘABLON DE COMPLETAT.** Câmpurile «...» se completează de operator / DPO. Procedură cerută de
> Legea 133/2011 (spec §7). Scopul: să existe un plan **înainte** de un incident, nu improvizat.

---

## 1. Scop și domeniu

Definește cum se detectează, evaluează, raportează și remediază o **încălcare a securității datelor cu
caracter personal** (mai ales date ale elevilor minori). Se aplică tuturor: personal, administratori,
furnizori.

## 2. Definiții

- **Incident de securitate:** orice eveniment care afectează confidențialitatea, integritatea sau
  disponibilitatea sistemului.
- **Încălcare a securității datelor (data breach):** un incident care duce la distrugere, pierdere,
  alterare, divulgare neautorizată sau acces neautorizat la date personale. **Doar acestea declanșează
  obligația de notificare.**

## 3. Echipa de răspuns și rolurile

| Rol | Persoană | Contact | Responsabilitate |
|---|---|---|---|
| Coordonator incident | «nume» | «tel/e-mail» | conduce răspunsul, decide notificarea |
| Responsabil protecția datelor (DPO) | «nume» | «...» | evaluează riscul, redactează notificările |
| Administrator tehnic | «nume» | «...» | izolează, investighează, remediază tehnic |
| Conducerea | «nume» | «...» | decizii, comunicare oficială |

## 4. Fluxul de răspuns

1. **Detectare & raportare internă (imediat).** Oricine observă un incident anunță Coordonatorul +
   Administratorul Tehnic. Se notează ora, ce s-a observat, cine raportează.
2. **Izolare (cât mai repede).** Oprește propagarea: revocă accesul compromis, schimbă parolele/
   token-urile, izolează sistemul afectat. NU șterge probe (jurnale, audit).
3. **Evaluare (DPO).** Sunt date personale afectate? Câte persoane, ce categorii, ce risc pentru ele?
   Folosește **jurnalul de audit** (`/admin/audits`) pentru a stabili ce s-a accesat/modificat.
4. **Clasificare gravitate** (vezi §5) → decide dacă se notifică autoritatea și/sau persoanele.
5. **Notificare** (vezi §6 și §7), în termenele legale.
6. **Remediere & închidere.** Repară cauza, restaurează din backup dacă e cazul, confirmă că breșa e
   închisă.
7. **Lecții învățate.** Înregistrează în registrul de incidente (§8); actualizează măsurile/DPIA.

## 5. Clasificarea gravității

| Nivel | Descriere | Notificare |
|---|---|---|
| Scăzut | fără date personale afectate / impact neglijabil | intern, documentat |
| Mediu | date personale afectate, risc limitat | CNPDCP «conform evaluării DPO» |
| Ridicat | date de minori expuse / risc ridicat pentru persoane | CNPDCP **și** persoanele vizate |

## 6. Notificarea autorității (CNPDCP)

- **Când:** la o încălcare care prezintă risc pentru drepturile persoanelor.
- **Termen:** fără întârziere nejustificată — orientativ în **72 de ore** de la luarea la cunoștință
  (aliniere GDPR). «Confirmați termenul exact aplicabil cu DPO/CNPDCP.»
- **Contact CNPDCP:** «adresă / e-mail / formular oficial».
- **Conținut minim:** natura încălcării, categoriile și numărul aproximativ de persoane/înregistrări,
  contactul DPO, consecințele probabile, măsurile luate/propuse. (Model în Anexa A.)

## 7. Notificarea persoanelor vizate (părinți/elevi)

- **Când:** dacă încălcarea prezintă **risc ridicat** pentru persoane.
- **Cum:** mesaj clar, în limbaj simplu — ce s-a întâmplat, ce date, ce riscuri, ce măsuri să ia
  persoana, contactul pentru întrebări. (Model în Anexa B.)
- Pentru minori: notificarea merge la părinte/reprezentantul legal.

## 8. Registrul de incidente

Toate incidentele (inclusiv cele nenotificate) se înregistrează: data, descriere, date afectate,
evaluare risc, decizie de notificare, măsuri, responsabil. «Locația registrului: ...»

---

## Anexa A — Model notificare CNPDCP

> Către: Centrul Național pentru Protecția Datelor cu Caracter Personal
> Subiect: Notificarea unei încălcări a securității datelor cu caracter personal
>
> Operator: IPL „Liceul Columna", «date».
> 1. Descrierea încălcării: «ce s-a întâmplat, când, cum a fost detectată».
> 2. Date și persoane afectate: «categorii, număr aproximativ».
> 3. Consecințe probabile: «...».
> 4. Măsuri luate/propuse: «izolare, remediere, prevenție».
> 5. Contact DPO: «nume, telefon, e-mail».
> Data, semnătura.

## Anexa B — Model notificare a persoanei vizate

> Stimate părinte/reprezentant legal,
> Vă informăm că la data de «...» a avut loc un incident care a afectat unele date personale ale
> copilului dumneavoastră: «ce date». Riscurile posibile sunt «...». Am luat următoarele măsuri: «...».
> Vă recomandăm să «...». Pentru întrebări: «contact DPO».
> Cu respect, conducerea IPL „Liceul Columna".
