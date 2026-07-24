# Tipurile de note și schema de notare

> **Sursa autoritativă:** `docs/surse/Tipuri-si-schema-notare.docx` — „LICEUL COLUMNA · Tipurile de
> note și schema de notare · Concretizare pentru catalogul electronic — anexă tehnică · Chișinău,
> iunie 2026". Documentul de față îl transcrie ca text căutabil și notează unde e implementat.
> La orice divergență, `.docx`-ul primează.
>
> Complementar cu `docs/STRUCTURA-CATALOG.md` (§2.4 formulele de medie, §3 pragul de promovare).

## 1. Tipurile de note și rolul lor în calcul

Tipul notei determină **cum intră aceasta în media semestrială**. Doar nota sumativă semestrială
(ESS la gimnaziu, teză la liceu) are tratament special — ponderare de 50%.

| # | Tip | Ce este | Cum intră în calcul |
|---|---|---|---|
| 1 | **Curentă (formativă)** | răspuns oral, fișă, temă, participare, test scurt | intră în MC; **neponderată individual** — toate curentele au greutate egală între ele |
| 2 | **ESI** — ev. sumativă intrasemestrială | testul sumativ de la finalul unei unități de învățare | **se comportă ca o notă curentă** — intră în MC alături de celelalte, nu separat; se distinge doar prin semnificația pedagogică |
| 3 | **Sumativa semestrială** | **ESS** la gimnaziu (discipline stabilite prin ordinul directorului), **teză** la liceu (discipline stabilite prin ordinul MEC) | **singura ponderată (50%)** |

La liceu **nu există ESS, doar teze**. Mecanica de calcul e însă aceeași la ambele niveluri:

```
MS = (MC + nota sumativă semestrială) / 2      condiționat de ambele ≥ 5,00
```

> **Esențial pentru baza de date** (citat din anexă): doar nota sumativă semestrială are tratament
> special la calcul. Curenta și ESI se comportă identic în formulă (ambele în MC); se disting doar
> ca etichetă.

## 2. Tabelul de referință `tip_nota`

Anexa cere explicit ca tipul să **nu** fie un câmp text liber, ci un tabel de referință care spune
motorului de calcul cum să trateze fiecare notă — „o schimbare de pondere se face dintr-un rând,
fără rescriere de cod".

| id | cod | denumire | intra_in_MC | ponderata | pondere |
|---|---|---|---|---|---|
| 1 | `CUR` | Curentă (formativă) | da | nu | — |
| 2 | `ESI` | Ev. sumativă intrasemestrială | da | nu | — |
| 3 | `ESS` | Ev. sumativă semestrială (gimnaziu) | nu | da | 0.50 |
| 4 | `TEZA` | Teză semestrială (liceu) | nu | da | 0.50 |

**Unde e implementat:** `App\Enums\EvaluationType` — `countsAsCurrent()` = `intra_in_MC`,
`isWeighted()` = `ponderata`, `weight()` = `pondere` (constanta `SUMMATIVE_WEIGHT = 0.50`).
`App\Actions\ComputeTermAverage` le citește, fără reguli codate rigid.

⚠️ **Devierea conștientă:** ESS și TEZA sunt UN singur caz de enum (`teza`), fiindcă au comportament
de calcul identic (`intra_in_MC = nu`, `pondere = 0.50`); se disting la afișare prin
`labelForCycle()` (gimnaziu → „ESS", liceu → „Teză"). Ordinul care stabilește disciplinele e
modelat separat (`summative_designations`).

## 3. Schema de notare, pe cicluri

**Primar (cl. I–IV)** — evaluare prin **descriptori** (independent / ghidat / cu mai mult sprijin)
și **calificative** (foarte bine / bine / suficient). *Suplimentar*, note pe scala 1–10 (regulament
intern Columna), cu nota de promovare 5,00.

**Gimnaziu (cl. V–IX) și Liceu (cl. X–XII)** — notare pe **scala 1–10**, cu media minimă de
promovare **5,00**.

> Mediile se calculează **până la sutimi, fără rotunjire**; promovarea se decide pe medie ≥ 5,00.

### 3.1 Nota individuală e ÎNTREAGĂ — sutimile aparțin mediilor

Anexa atribuie sutimile **explicit și exclusiv mediilor** („mediile se calculează până la sutimi"),
iar notei îi dă doar scala 1–10. Nu conține fraza literală „nota este un număr întreg", dar
distincția e fără echivoc, iar **datele școlii o confirmă unanim: din cele 52.228 de note importate
din sistemul vechi, NICIUNA nu are zecimale**.

Regula e aplicată pe trei niveluri (commit `a7690ed`):
- `Grade::saving` respinge valorile zecimale — orice cale prin model (panou, seedere, viitor API);
- formularul din panou: `->step(1)->rules(['integer'])`, client și server;
- `app:fix-decimal-grades` repară datele deja stricate și recalculează mediile atinse.

Importul legacy scrie prin query builder și **nu** trece prin gardă — deliberat: reproduce fidel
istoricul școlii.

## 4. Ce NU e încă acoperit de implementare

⚠️ **Primar: „suplimentar, note pe scala 1–10" lângă calificative.** Anexa spune că la primar
calificativele și notele numerice coexistă, iar datele reale o confirmă — trei discipline primare au
ambele forme: Matematică (1.022 note numerice + 2.067 calificative), Limba și literatura română
(2.209 + 3.321), Limba străină 1 (11 + 572).

Formularul din panou arată însă câmpul numeric **doar** când `Subject::grading_type = Numeric`, iar
aceste discipline sunt marcate `cd`/`d` — deci un învățător **nu poate introduce astăzi din panou o
notă numerică** la ele, deși școala o face. Datele istorice sunt intacte (importul nu trece prin
formular); afectat e doar fluxul de introducere curentă. **Neremediat — decizie de produs.**
