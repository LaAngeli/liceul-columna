# Acces SSH la Hostinger (de la zero)

Scop: o cheie SSH dedicată pentru `columna.md`, adăugată la Hostinger, cu o intrare curată în
`~/.ssh/config`, astfel încât `ssh columna-host` să meargă. Mediul de dev e **Windows + OpenSSH**
(`C:\Windows\System32\OpenSSH\ssh.exe`).

## 1. Generează cheia (local, sigur — nu e acțiune sensibilă)

O cheie **per proiect** (ca `synaptica_host`, `energix_host` existente). ed25519, modernă:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/columna_host -C "columna-deploy"
```

- `-f ~/.ssh/columna_host` → creează `columna_host` (privată) + `columna_host.pub` (publică).
- **Passphrase:** recomandat una + `ssh-agent` (mai jos). Dacă deploy-ul e automatizat prin cron/CI
  fără agent, o cheie fără passphrase e compromisul uzual — decide cu utilizatorul. Nu forța.
- ⚠️ NU suprascrie o cheie existentă. Dacă `columna_host` există deja, alege alt nume sau confirmă.
- 🔒 Fișierul privat `columna_host` **nu se citește/afișează/copiază** nicăieri. Se folosește doar
  cheia publică `columna_host.pub`.

Pe Windows, pornește agentul o singură dată (ca serviciu) și adaugă cheia:

```powershell
Get-Service ssh-agent | Set-Service -StartupType Automatic
Start-Service ssh-agent
ssh-add $env:USERPROFILE\.ssh\columna_host
```

## 2. Adaugă cheia PUBLICĂ la Hostinger (acțiune a utilizatorului, în panou)

Conținutul de adăugat = **cheia publică** (`~/.ssh/columna_host.pub`, o singură linie
`ssh-ed25519 AAAA... columna-deploy`). Afișeaz-o utilizatorului să o copieze:

```bash
cat ~/.ssh/columna_host.pub
```

- **Web / Business hosting:** hPanel → *Advanced* → **SSH Access** → activează SSH + *Manage SSH keys*
  → *Import SSH Key* → lipește linia `.pub`. Reține din pagină: **hostname**, **user `uXXXXXXXX`**,
  **port `65002`**.
- **VPS:** hPanel → *VPS* → serverul tău → *Settings* → **SSH Keys** → *Add SSH Key* → lipește `.pub`.
  (Ideal adăugată la crearea VPS-ului.) Reține **IP-ul** și userul (`root` implicit). Portul e `22`
  dacă nu l-ai schimbat.

> Introducerea cheii în panou, crearea VPS-ului și orice login în contul Hostinger = **pașii
> utilizatorului** (necesită credențiale de cont). Eu pregătesc cheia + configul; tu le adaugi în panou.

## 3. Intrarea în `~/.ssh/config`

Respectă stilul host-urilor existente. **Completează valorile reale** primite de la Hostinger (întreabă
utilizatorul — nu inventa IP/user).

**Web / Business hosting:**
```
Host columna-host
    HostName <host-din-hpanel>
    Port 65002
    User uXXXXXXXX
    IdentityFile ~/.ssh/columna_host
    IdentitiesOnly yes
    StrictHostKeyChecking accept-new
```

**VPS:**
```
Host columna-host
    HostName <VPS_IP>
    Port 22
    User root
    IdentityFile ~/.ssh/columna_host
    IdentitiesOnly yes
    StrictHostKeyChecking accept-new
```

`IdentitiesOnly yes` = trimite DOAR această cheie (altfel agentul le încearcă pe toate și serverul
poate refuza după prea multe încercări). `accept-new` = acceptă automat host key-ul nou prima dată,
dar refuză dacă se schimbă (protecție MITM păstrată).

## 4. ⚠️ Gotcha Windows: `~/.ssh/config` trebuie UTF-8 **fără BOM**

În `~/.ssh` există deja un `config.bak-bom` — urma unei probleme reale: PowerShell `Out-File` /
`Set-Content` scriu implicit **UTF-16 LE cu BOM**, iar OpenSSH nu parsează un config cu BOM →
`Bad configuration option`. Reguli:

- Editează `~/.ssh/config` cu un editor care salvează **UTF-8 fără BOM** (sau prin tool-ul Edit, nu
  prin `Out-File`).
- Dacă trebuie din PowerShell, forțează fără BOM:
  ```powershell
  $utf8 = New-Object System.Text.UTF8Encoding($false)
  [System.IO.File]::WriteAllText("$env:USERPROFILE\.ssh\config", $continut, $utf8)
  ```
- Permisiuni: pe Windows, OpenSSH e mai tolerant decât pe Linux, dar cheia privată nu trebuie să fie
  „prea deschisă". Dacă apare `UNPROTECTED PRIVATE KEY FILE`, restrânge ACL-ul:
  ```powershell
  icacls "$env:USERPROFILE\.ssh\columna_host" /inheritance:r /grant:r "$($env:USERNAME):(R)"
  ```

## 5. Prima conectare + verificare (read-only, sigur)

```bash
ssh columna-host 'whoami; pwd; php -v; php artisan --version 2>/dev/null || echo "app nedeployată încă"'
```

- Prima dată, `accept-new` salvează host key-ul în `~/.ssh/known_hosts` fără prompt.
- Confirmă: userul corect, versiunea PHP (trebuie **8.3+** pentru Laravel 13), locația.
- **Web hosting:** dacă `php` implicit e vechi, versiunea corectă poate fi la o cale explicită
  (ex. `/usr/bin/php8.3` sau `/opt/alt/php83/usr/bin/php`) — verifică în hPanel selectorul PHP și
  folosește calea completă în comenzile de deploy.

Dacă conectarea eșuează:
- `ssh -v columna-host 'exit'` → citește linia de debug (cheie greșită, port închis, user greșit).
- „Permission denied (publickey)" → cheia publică nu e (încă) în panou, sau `User`/`IdentityFile`
  greșit în config.
- Timeout → port/IP greșit sau IP-ul tău blocat de firewall-ul serverului (hPanel → firewall).

## 6. Ce urmează

Cu `ssh columna-host` funcțional → treci la [deploy-runbook.md](deploy-runbook.md) pentru prima
instalare sau redeploy. Reține tipul de mediu (web hosting vs VPS) — determină restul pașilor.
