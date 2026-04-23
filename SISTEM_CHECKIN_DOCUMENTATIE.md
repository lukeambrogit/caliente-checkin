# Caliente Dance Studio — Documentație Sistem Check-In

> **Data:** Aprilie 2026  
> **Versiune plugin:** 2.0.0  
> **Stack:** WordPress 6.8 · PHP 8.3 · React 18 (CRA 5) · Node.js 22

---

## Cuprins

1. [Privire de ansamblu — cum funcționează](#1-privire-de-ansamblu)
2. [Arhitectura sistemului](#2-arhitectura-sistemului)
3. [WordPress — Plugin `membership-validator`](#3-wordpress--plugin-membership-validator)
4. [Aplicația React (Check-in UI)](#4-aplicația-react-check-in-ui)
5. [Serverul WebSocket Node.js](#5-serverul-websocket-nodejs)
6. [Fluxul complet al unei scanări QR](#6-fluxul-complet-al-unei-scanări-qr)
7. [Dashboard-ul de prezențe (live)](#7-dashboard-ul-de-prezențe-live)
8. [Autentificare REST API](#8-autentificare-rest-api)
9. [Baza de date](#9-baza-de-date)
10. [Configurare și deployment](#10-configurare-și-deployment)
11. [Depanare rapidă](#11-depanare-rapidă)

---

## 1. Privire de ansamblu

Sistemul conectează **WordPress** (care gestionează membrii și abonamentele) cu o **aplicație React** de check-in montat direct în WordPress printr-un shortcode. Un **server Node.js** face ca dashboard-ul de prezențe să se actualizeze **în timp real**, fără reîncărcare de pagină.

### Componentele principale

| Componentă | Locație | Rol |
|---|---|---|
| Plugin WP `membership-validator` | `calientedancestudio.ro/wp-content/plugins/membership-validator/` | Stochează membrii, validează abonamentele, expune REST API |
| Aplicație React | `caliente_web_ui/` (sursă) → build deploiat în plugin | Interfața de scanare QR și dashboard |
| Server WebSocket | `caliente_ws_server/` | Relay live events: WordPress → dashboard-uri deschise |

---

## 2. Arhitectura sistemului

```
┌─────────────────────────────────────────────────────────────────┐
│  Browser (tab 1) — Pagina de Check-in                          │
│  React: <CheckInPage />                                         │
│  - Scanează QR cu camera                                       │
│  - Apelează GET /wp-json/caliente/v1/validate-qr               │
└────────────────────┬────────────────────────────────────────────┘
                     │  HTTP GET
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│  WordPress REST API  (caliente/v1)                              │
│  class-oc-membership-rest-api.php                               │
│  - Verifică autentificarea (Bearer token + device ID)          │
│  - Validează abonamentul din baza de date                       │
│  - Scrie în tabelul de log                                      │
│  - POSTează evenimentul la serverul WS  (non-blocking)         │
└────────────┬───────────────────────────────────────────────────┘
             │  HTTP POST /broadcast
             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Server WebSocket Node.js  (localhost:3001)                     │
│  caliente_ws_server/server.js                                   │
│  - Validează X-WS-Secret header                                │
│  - Trimite evenimentul prin WebSocket la toți clienții conectați│
└────────────┬───────────────────────────────────────────────────┘
             │  WebSocket push
             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Browser (tab 2) — Dashboard Prezențe                           │
│  React: <AttendanceDashboard />                                 │
│  - Primește evenimentul instant                                 │
│  - Adaugă rândul în tabel fără reload                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. WordPress — Plugin `membership-validator`

### 3.1 Fișiere principale

```
wp-content/plugins/membership-validator/
├── membership-validator.php              ← punct de intrare, definește constante
├── includes/
│   ├── class-oc-react-checkin-page.php  ← shortcode-uri + injectare config
│   ├── class-oc-dashboard.php           ← pagina de setări din WP Admin
│   └── addons/membership-validator/
│       └── class-oc-membership-rest-api.php  ← REST API (validate-qr, checkins)
└── templates/
    └── checkin-settings-page.php        ← template HTML pagina admin
```

### 3.2 Constante definite de plugin

| Constantă | Valoare exemplu |
|---|---|
| `OC_PLUGIN_DIR` | `...wp-content/plugins/membership-validator/` |
| `OC_PLUGIN_URL` | `https://calientedancestudio.ro/wp-content/plugins/membership-validator/` |
| `OC_PLUGIN_VERSION` | `2.0.0` |

### 3.3 Shortcode-uri

#### `[oc_checkin_app]`
Montează aplicația React de **scanare check-in** pe orice pagină WordPress.

#### `[oc_checkin_dashboard]`
Montează aplicația React în modul **dashboard prezențe** pe orice pagină WordPress.

Ambele shortcode-uri:
1. Verifică dacă build-ul React există în `assets/react-checkin/`
2. Enqueue-ează CSS și JS din `asset-manifest.json`
3. Injectează `window.OC_CHECKIN_CONFIG` ca JavaScript inline

**Obiectul `window.OC_CHECKIN_CONFIG` injectat:**

```js
window.OC_CHECKIN_CONFIG = {
  apiBaseUrl:     "https://calientedancestudio.ro/wp-json/caliente/v1",
  deviceId:       "studio-checkin-1",   // ID dispozitiv, din setări WP Admin
  apiToken:       "abc123...",          // Token Bearer, din setări WP Admin
  nonce:          "wp_nonce_value",     // Nonce WordPress REST
  uploadsBaseUrl: "https://calientedancestudio.ro/wp-content/uploads",
  mode:           "checkin",            // sau "dashboard"
  wsUrl:          "http://localhost:3001"  // URL server WebSocket
};
```

React citește acest obiect pentru a ști unde să facă requesturi. **Nu există date hardcodate în build.**

### 3.4 REST API — Endpoints `caliente/v1`

Toate endpoint-urile necesită autentificare (vezi [Secțiunea 8](#8-autentificare-rest-api)).

#### `GET /wp-json/caliente/v1/validate-qr`

Validează un cod QR scanat.

| Parametru | Tip | Obligatoriu | Descriere |
|---|---|---|---|
| `token` | string | ✅ | Codul scanat (JSON `{"user_id":206}` sau ID simplu ca `"206"`) |
| `device_id` | string | ❌ | ID-ul dispozitivului care scanează |

**Răspuns succes (200):**
```json
{
  "success": true,
  "data": {
    "user_id": 206,
    "user_name": "Mihaela Neamtu",
    "product_name": "Abonament 10 sedinte",
    "sessions_remaining": 7,
    "sessions_total": 10,
    "expires_at": "2026-05-31",
    "status": "active"
  }
}
```

**Răspuns blocat (409):**
```json
{
  "success": false,
  "code": "ALREADY_CHECKED_IN",
  "message": "Mihaela Neamtu a intrat deja astăzi.",
  "data": {
    "user_id": 206,
    "user_name": "Mihaela Neamtu",
    ...
  }
}
```

**Răspuns negăsit (404):**
```json
{
  "success": false,
  "code": "NOT_FOUND",
  "message": "Codul scanat nu a fost găsit."
}
```

#### `GET /wp-json/caliente/v1/checkins`

Returnează log-ul de check-in-uri pentru o zi.

| Parametru | Valoare default | Descriere |
|---|---|---|
| `date` | data curentă | Format `YYYY-MM-DD` |
| `limit` | `100` | Maxim înregistrări returnate |

**Răspuns:**
```json
{
  "success": true,
  "data": {
    "date": "2026-04-22",
    "count": 15,
    "entries": [
      {
        "id": 42,
        "user_id": 206,
        "user_name": "Mihaela Neamtu",
        "product_name": "Abonament 10 sedinte",
        "status": "success",
        "result_code": "OK",
        "time": "2026-04-22 09:30:15",
        "device_id": "studio-checkin-1",
        "error": ""
      }
    ]
  }
}
```

### 3.5 Notificarea serverului WebSocket

După fiecare scanare (reușită **sau** blocată), WordPress apelează în mod **non-blocking** (fire-and-forget) serverul WebSocket:

```php
// Din class-oc-membership-rest-api.php
private function notify_ws_server(array $event): void {
    $ws_url    = get_option('oc_ws_server_url', '');  // ex: http://localhost:3001
    $ws_secret = get_option('oc_ws_server_secret', '');

    wp_remote_post(
        trailingslashit($ws_url) . 'broadcast',
        [
            'timeout'  => 2,
            'blocking' => false,   // nu așteaptă răspunsul — nu încetinește API-ul
            'headers'  => [
                'Content-Type' => 'application/json',
                'X-WS-Secret'  => $ws_secret,
            ],
            'body' => wp_json_encode($event),
        ]
    );
}
```

Evenimentul trimis conține:
```json
{
  "user_id": 206,
  "user_name": "Mihaela Neamtu",
  "product_name": "Abonament 10 sedinte",
  "status": "success",
  "result_code": "OK",
  "error": "",
  "time": "2026-04-22 09:30:15",
  "device_id": "studio-checkin-1",
  "date": "2026-04-22"
}
```

### 3.6 Setări WP Admin

Pagina de setări: **WP Admin → Membership Validator → Check-in Settings**
(`wp-admin/admin.php?page=membership-validator-checkin`)

| Setare | Opțiune DB | Descriere |
|---|---|---|
| API Token | `oc_checkin_api_token` | Token-ul Bearer pe care React îl trimite |
| Device ID | `oc_checkin_device_id` | ID-ul dispozitivului implicit |
| WS Server URL | `oc_ws_server_url` | URL server Node.js (ex: `http://localhost:3001`) |
| WS Server Secret | `oc_ws_server_secret` | Secret comun WordPress ↔ server Node |
| Dispozitive autorizate | `oc_membership_api_devices` | Array de dispozitive cu api_key_hash |

---

## 4. Aplicația React (Check-in UI)

### 4.1 Structura fișierelor sursă

```
caliente_web_ui/src/
├── App.jsx                    ← router simplu: mode=checkin sau dashboard
├── index.js                   ← punct de intrare React
├── api/
│   └── clientCheck.js         ← apeluri REST API WordPress
├── components/
│   ├── ClientCheckIn.jsx      ← UI principal de scanare + afișare rezultat
│   ├── QRCodeScanner.jsx      ← scanare QR cu camera (zxing)
│   ├── CameraFeed.jsx         ← preview cameră
│   ├── TestBarcodesSection.jsx ← coduri de test afișate ca imagini QR
│   └── AttendanceDashboard.jsx ← dashboard live prezențe
├── pages/
│   ├── CheckInPage.jsx        ← pagina de check-in (orchestrare)
│   └── DashboardPage.jsx      ← wrapper pentru dashboard
├── data/
│   └── testBarcodes.js        ← ID-uri membri de test (206, 220)
└── utils/
```

### 4.2 Rutare (App.jsx)

React citește `window.OC_CHECKIN_CONFIG.mode` injectat de PHP:

```jsx
// App.jsx
const mode = window.OC_CHECKIN_CONFIG?.mode;

if (mode === 'dashboard') return <DashboardPage />;
return <CheckInPage />;
```

Astfel, același build React servește **ambele shortcode-uri** — diferă doar ce injectează PHP.

### 4.3 Fluxul de scanare în ClientCheckIn

1. Dacă camera este disponibilă → arată pasul `face` (detecție față) → trece la `scan` (QR)
2. Dacă camera **nu** este disponibilă (HTTP, sau lipsă permisiuni) → sare direct la `scan`
3. Pasul `scan`: scanare QR cu `@zxing/browser` sau input manual
4. Codul scanat este trimis la `getClientByCode(code)` → REST API WordPress
5. Rezultat afișat:
   - ✅ Verde — intrare reușită
   - ⚠️ Portocaliu — blocat (deja intrat, abonament expirat etc.)
   - ❌ Roșu — negăsit

### 4.4 Formatul codului QR

Codurile QR generate de plugin conțin JSON:

```json
{"user_id": 206, "type": "member_id"}
```

Sau, pentru compatibilitate, un simplu string numeric: `"206"`.

API-ul WordPress acceptă ambele formate prin funcția `validate_qr_token()`.

### 4.5 Build și deployment

```powershell
# Din directorul caliente_web_ui/
npm run build

# Deployment manual în plugin
$dest = "C:\laragon\www\calientedancestudio.ro\wp-content\plugins\membership-validator\assets\react-checkin"
Remove-Item $dest -Recurse -Force
Copy-Item ".\build" $dest -Recurse
```

Build-ul este configurat cu:
```
PUBLIC_URL=/wp-content/plugins/membership-validator/assets/react-checkin
```
(în `.env`) astfel încât WordPress să încarce assets-urile de la adresa corectă.

---

## 5. Serverul WebSocket Node.js

### 5.1 Locație și fișiere

```
caliente_ws_server/
├── server.js       ← serverul propriu-zis
├── package.json    ← dependențe: express, ws, dotenv
├── .env            ← configurare locală (nu se comite în git)
└── .env.example    ← template configurare
```

### 5.2 Funcționare

Serverul Node.js expune **HTTP și WebSocket pe același port** (implicit 3001):

- **`GET /health`** — verificare stare, returnează `{ ok: true, clients: N }`
- **`POST /broadcast`** — primit de la WordPress cu evenimentul de check-in; broadcastează la toți clienții WS conectați
- **WebSocket `/`** — browser-ele (dashboard React) se conectează aici pentru a primi events live

### 5.3 Configurare `.env`

```ini
PORT=3001
WS_SECRET=caliente_ws_secret_2025   # Trebuie să coincidă cu setarea din WP Admin
ALLOWED_ORIGIN=http://calientedancestudio.ro.test
```

### 5.4 Pornire server

```powershell
Set-Location "C:\laragon\www\caliente_ws_server"
node server.js
```

Output așteptat:
```
[WS] Caliente WebSocket server running on port 3001
[WS] Health: http://localhost:3001/health
[WS] Broadcast endpoint: POST http://localhost:3001/broadcast
```

### 5.5 Securitate

- WordPress trimite header `X-WS-Secret: {secret}` la fiecare POST
- Serverul respinge orice request fără secretul corect cu `401 Unauthorized`
- Secretul este stocat în WP ca opțiune DB (`oc_ws_server_secret`) și în `.env` al serverului Node

---

## 6. Fluxul complet al unei scanări QR

```
Receptor (tablet/telefon cu pagina de check-in deschisă)
│
│  1. Camera scanează codul QR al membrului
│     Codul extras: {"user_id": 206, "type": "member_id"}
│
│  2. React → GET /wp-json/caliente/v1/validate-qr?token=...&device_id=studio-checkin-1
│             Header: Authorization: Bearer {api_token}
│
│  3. WordPress:
│     a. Verifică autentificarea (token + device_id)
│     b. Parsează token → user_id = 206
│     c. Caută membrul în DB: Mihaela Neamtu
│     d. Verifică abonamentul activ
│     e. Verifică dacă nu a mai intrat astăzi
│     f. Decrementează sesiunile rămase
│     g. Scrie în 18SpyX5e_membership_validation_log
│     h. POST http://localhost:3001/broadcast (non-blocking, nu așteaptă)
│     i. Returnează 200 cu datele membrului
│
│  4. React (CheckInPage):
│     - Primește { success: true, data: { user_name: "Mihaela Neamtu", ... } }
│     - Afișează ✅ verde cu numele și abonamentul
│
│  5. Server WebSocket:
│     - Primește POST /broadcast de la WordPress
│     - Validează X-WS-Secret
│     - Trimite prin WebSocket la toți clienții conectați:
│       { type: "checkin", data: { user_id: 206, user_name: "Mihaela Neamtu", status: "success", ... } }
│
│  6. Dashboard (alt browser/tab deschis pe pagina de dashboard):
│     - Primește evenimentul WebSocket
│     - Prepend rândul nou în tabelul de prezențe (fără reload)
│     - Actualizează contoarele (Total / Intrați / Blocați)
```

---

## 7. Dashboard-ul de prezențe (live)

### 7.1 Cum funcționează

Componenta `AttendanceDashboard` folosește o **strategie duală**:

1. **WebSocket** (primar): se conectează la `ws://localhost:3001` la montare. Când primește un eveniment `{type:"checkin"}`, adaugă rândul în timp real.
2. **Polling REST** (fallback): dacă WebSocket-ul este indisponibil sau se deconectează, pornește un interval de 10 secunde care apelează `GET /checkins` și actualizează lista.

Când WebSocket-ul se reconectează, polling-ul se oprește automat.

### 7.2 Indicatorul de status

| Icon | Stare | Semnificație |
|---|---|---|
| 🟢 Live (WebSocket) | `connected` | Actualizare instantă la fiecare scanare |
| 🟡 Polling 10s | `disconnected` | Server WS oprit sau inaccesibil; refresh automat la 10s |
| 🔴 WS eroare | `error` | Eroare conexiune; polling activ |

### 7.3 Utilizare în WordPress

Creați o pagină WordPress și adăugați shortcode-ul:
```
[oc_checkin_dashboard]
```

---

## 8. Autentificare REST API

### 8.1 Metoda principală: Bearer Token

```http
GET /wp-json/caliente/v1/validate-qr?token=206
Authorization: Bearer {api_token}
X-Device-Id: studio-checkin-1
```

Token-ul este stocat în WP Admin ca `oc_checkin_api_token` și injectat automat în `window.OC_CHECKIN_CONFIG.apiToken`.

### 8.2 Metoda alternativă: API Key per dispozitiv

```http
GET /wp-json/caliente/v1/validate-qr?token=206
X-Api-Key: {api_key_hash}
X-Device-Id: studio-checkin-1
```

Dispozitivele autorizate sunt stocate în WP option `oc_membership_api_devices` ca array cheiat după `device_id`.

---

## 9. Baza de date

### Prefix tabele: `18SpyX5e_`

### Tabel principal de log: `18SpyX5e_membership_validation_log`

| Coloană | Tip | Descriere |
|---|---|---|
| `id` | INT | PK auto-increment |
| `membership_id` | INT | ID intrare membru |
| `user_id` | INT | ID user WordPress |
| `validation_method` | VARCHAR | `api` |
| `validation_status` | ENUM | `success`, `failed`, `error` |
| `validation_date` | DATETIME | Momentul scanării |
| `ip_address` | VARCHAR | IP dispozitiv |
| `user_agent` | VARCHAR | Browser/app |
| `validation_metadata` | JSON | Date extra (device_id, endpoint etc.) |
| `error_message` | TEXT | Mesajul de eroare dacă a eșuat |

### Opțiuni WordPress relevante (`18SpyX5e_options`)

| `option_name` | Descriere |
|---|---|
| `oc_checkin_api_token` | Token Bearer pentru autentificare React app |
| `oc_checkin_device_id` | Device ID default |
| `oc_ws_server_url` | URL server WebSocket (ex: `http://localhost:3001`) |
| `oc_ws_server_secret` | Secret comun WP ↔ server WS |
| `oc_membership_api_devices` | Array dispozitive autorizate cu API key |

---

## 10. Configurare și deployment

### 10.1 Mediu local (Laragon)

| Serviciu | URL |
|---|---|
| WordPress | `http://calientedancestudio.ro.test` |
| REST API | `http://calientedancestudio.ro.test/wp-json/caliente/v1` |
| WS Server | `http://localhost:3001` |

### 10.2 Pași setup complet pe un server nou

**1. Clonare/copiere fișiere**
```
caliente_web_ui/          → mașina de build
caliente_ws_server/       → server (VPS sau localhost)
calientedancestudio.ro/   → server web (WordPress)
```

**2. Build și deploy React**
```powershell
cd caliente_web_ui
npm install
npm run build
# Copiați build/ în wp-content/plugins/membership-validator/assets/react-checkin/
```

**3. Configurare server WebSocket**
```bash
cd caliente_ws_server
npm install
cp .env.example .env
# Editați .env: setați PORT, WS_SECRET, ALLOWED_ORIGIN
node server.js
```

**4. Configurare WordPress**
- WP Admin → Membership Validator → Check-in Settings
- Setați **WS Server URL** = `http://localhost:3001` (sau URL-ul serverului Node)
- Setați **WS Server Secret** = același secret din `.env`
- Setați **API Token** — copiați valoarea în `.env` al React dacă faceți build local

**5. Creare pagini WordPress**
- Pagina Check-in: shortcode `[oc_checkin_app]`
- Pagina Dashboard: shortcode `[oc_checkin_dashboard]`

### 10.3 Pentru producție

Pe producție (VPS), serverul WebSocket trebuie pornit ca serviciu permanent:
```ini
# /etc/systemd/system/caliente-ws.service
[Unit]
Description=Caliente WebSocket Server

[Service]
WorkingDirectory=/var/www/caliente_ws_server
ExecStart=/usr/bin/node server.js
Restart=always

[Install]
WantedBy=multi-user.target
```

De asemenea, pentru WebSocket pe HTTPS, adăugați un **reverse proxy Nginx**:
```nginx
location /ws/ {
    proxy_pass http://localhost:3001/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

Și actualizați `ALLOWED_ORIGIN` în `.env` cu domeniul real.

---

## 11. Depanare rapidă

### Dashboard nu se actualizează în timp real

1. Verificați că serverul Node rulează:
   ```
   http://localhost:3001/health
   → {"ok":true,"clients":1}
   ```
2. Verificați că `oc_ws_server_url` este setat în WP Admin (câmpul nu poate fi gol)
3. Verificați că secretul din WP Admin coincide cu `WS_SECRET` din `.env`
4. Deschideți consola browser pe pagina dashboard — dacă vedeți `🟡 Polling 10s`, conexiunea WS eșuează

### Scanare returnează 401 Unauthorized

- Verificați că `oc_checkin_api_token` din WP Admin coincide cu ce trimite React
- Verificați header-ul `Authorization: Bearer {token}` în Network tab

### Scanare returnează 404 Not Found

- User-ul nu există sau nu are abonament activ în baza de date
- Verificați că QR-ul conține un user_id valid (`{"user_id": 206}`)

### Build React eșuează

```powershell
# Ștergeți cache-ul și reinstalați
Remove-Item node_modules -Recurse -Force
npm install
npm run build
```

### Camera nu funcționează

- Camera necesită **HTTPS** sau `localhost`. Pe HTTP (LAN) camera nu este disponibilă din motive de securitate browser.
- Aplicația detectează automat absența camerei și sare la input manual.

---

*Documentație generată pentru sistemul Caliente Dance Studio check-in v2.0.0*
