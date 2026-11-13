=== CookieKit ===
Contributors: cookiekit
Tags: cookie, gdpr, cookie banner, consent, garante privacy, consent mode v2
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Consent Management Platform self-hosted, conforme al provvedimento del Garante Privacy n. 231 del 10 giugno 2021.

== Description ==

CookieKit è una Consent Management Platform leggera, self-hosted, libera e gratuita, utilizzabile da qualunque sito WordPress che voglia raccogliere il consenso ai cookie in modo conforme alle linee guida del Garante Privacy italiano e al GDPR. Pensata per editori indipendenti, blog, e-commerce e siti istituzionali che non monetizzano con Google AdSense / Ad Manager (i quali richiedono una CMP certificata IAB TCF v2.2).

= Caratteristiche =

* Banner conforme al provvedimento Garante Privacy n. 231/2021 con pulsanti "Accetta tutti", "Rifiuta tutti" e "Personalizza" di pari dignità grafica
* Pulsante "X" di chiusura banner = rifiuto (mai consenso implicito)
* Auto-blocco di script di terze parti (Google Analytics, GTM, YouTube, Vimeo, Maps, ecc.) finché non c'è consenso
* Sostituzione iframe con placeholder click-to-load
* Google Consent Mode v2 Basic (segnali ad_storage, analytics_storage, ad_user_data, ad_personalization, ecc.)
* Pulsante "Gestisci cookie" persistente nel footer (icona)
* Re-prompt automatico dopo N giorni (default 180, conforme Garante)
* Re-prompt forzato quando cambia la versione della policy
* Log consensi su tabella DB (UUID, azione, categorie, hash IP, UA, URL pagina, lingua, timestamp)
* Esportazione log in CSV o JSON
* **Scanner doppio**: passivo server-side (cattura cookie tipo Cloudflare e PHP) + browser client-side multi-pagina (cattura cookie JavaScript come `_ga`, `_fbp`, `VISITOR_PRIVACY_METADATA`, ecc., compresi gli HttpOnly via integrazione `$_COOKIE`)
* Banner mostra **solo cookie effettivamente rilevati** dallo scanner, raggruppati per categoria con tabella espandibile (Nome · Fornitore · Durata · Finalità) — pattern Cookie Script / Cookiebot
* Classifier intelligente che riconosce cookie noti via pattern regex e via match di dominio (es. cookie su `.youtube.com` → categoria marketing/YouTube)
* Pannello admin completo (Impostazioni · Categorie · Vendor · Scanner · Log · Testi)
* Shortcode `[ckit_button]` per inserire link "Gestisci cookie" in qualsiasi punto
* Endpoint REST `POST /wp-json/cookiekit/v1/consent`
* Multi-categoria configurabile
* Tema light/dark
* Conforme al provvedimento Garante Privacy 231/2021, GDPR, ePrivacy, Codice Privacy

= Cosa NON include =

* IAB TCF v2.2 (richiede registrazione formale presso IAB Europe come CMP, non gestita)
* Google AdSense / Ad Manager personalizzato richiede CMP certificata IAB. Senza certificazione la pubblicità Google sarà non personalizzata (NPA)

== Installation ==

1. Carica la cartella `cookie-kit` nella directory `/wp-content/plugins/`
2. Attiva il plugin dal menu "Plugin" di WordPress
3. Vai su "Cookie Consent" nel menu admin per configurare
4. Esegui la prima scansione dal tab "Scanner"
5. Configura i vendor del tuo sito nel tab "Vendor"
6. Personalizza i testi nel tab "Testi e aspetto"

== Frequently Asked Questions ==

= Il plugin è conforme al GDPR? =

Sì, il plugin implementa i requisiti del Regolamento (UE) 2016/679 (GDPR), del provvedimento del Garante Privacy n. 231 del 10 giugno 2021 e della Direttiva ePrivacy. Le scelte dell'utente sono documentate (UUID, timestamp, versione policy, scelte). La conformità complessiva del sito dipende anche dalla corretta configurazione dei vendor e dalle policy testuali pubblicate.

= Posso usare Google AdSense con questo plugin? =

Tecnicamente sì, ma Google dal 16 gennaio 2024 richiede una CMP certificata IAB TCF v2.2 per servire annunci personalizzati nello SEE. Senza certificazione, AdSense mostrerà solo annunci non personalizzati (NPA) con CPM tipicamente più basso. Per i siti che monetizzano principalmente con affiliate marketing e usano Google solo per Analytics/GTM, il plugin è perfetto.

= Come funziona l'auto-blocco script? =

Il plugin filtra l'output WordPress (hook `script_loader_tag`) e sostituisce gli script di tracciamento conosciuti (configurati nel tab Vendor) con `<script type="text/plain" data-ckit-src="...">`. Quando l'utente acconsente, JavaScript riconverte questi tag in script attivi.

= I log sono conformi al GDPR? =

Sì. L'IP non viene salvato in chiaro ma in forma di hash SHA-256 con salt. La user-agent string è troncata a 500 caratteri. La retention default è 1825 giorni (5 anni) — modificabile dalle impostazioni.

== Screenshots ==

1. Banner principale
2. Modale di personalizzazione preferenze
3. Pannello admin — Impostazioni
4. Pannello admin — Scanner cookie
5. Pannello admin — Log consensi

== Changelog ==

= 1.1.2 =
* Scanner client-side ora cattura anche cookie HttpOnly (PHPSESSID, ecc.) integrando `$_COOKIE` lato server con la lista da `document.cookie`.

= 1.1.1 =
* Modalità snapshot: blocker e iframe-replacement disattivati durante la scansione browser così script terzi e iframe si caricano nativi.
* Snapshot multi-pagina: home + 2 pagine + 2 post random.
* Classifier ora matcha anche per dominio del cookie con host del vendor (es. `.youtube.com` auto-classificato come YouTube).
* Aggiunto `VISITOR_PRIVACY_METADATA` al dizionario meta e al vendor YouTube preimpostato.

= 1.1.0 =
* Scanner browser client-side: pulsante "▶ Avvia scansione browser" nel tab Scanner. JS cattura `document.cookie` dopo aver simulato accept all e attesa script di tracking.
* Endpoint REST `POST /cookiekit/v1/snapshot` protetto da `manage_options`.
* Banner ora mostra **solo cookie reali** della tabella scan (non più dichiarati staticamente nei vendor).

= 1.0.3 =
* Ridotti vendor preimpostati ai 6 più universali (GA4, GTM, YouTube, Vimeo, Maps, Cloudflare). Rimossi Microsoft Clarity, Hotjar, Facebook Pixel per evitare informativa fuorviante quando non in uso.

= 1.0.2 =
* Banner: sotto ogni categoria del modale di personalizzazione c'è ora un toggle "▸ Mostra cookie (N)" con tabella espandibile (Nome · Fornitore · Durata · Finalità). Pattern Cookie Script / iubenda.
* Aggiunto dizionario interno con metadata di ~30 cookie noti (durata + descrizione in italiano).
* Badge 1ª/3ª parte sui cookie nel modale.

= 1.0.1 =
* Classifier riconosce cookie Cloudflare (`__cf_bm`, `__cflb`, `cf_clearance`, ...) come tecnici/necessary.
* Classifier riconosce token CSRF/sicurezza form (`_csrf`, `XSRF-TOKEN`, ...) come tecnici.
* Classifier riconosce cookie WooCommerce (`woocommerce_*`, `wc_*`) come tecnici.
* Aggiunto Cloudflare ai vendor preimpostati.

= 1.0.0 =
* Prima release. Banner, auto-blocco, scanner passivo, log, Consent Mode v2, pannello admin completo, REST endpoint, shortcode.

== Upgrade Notice ==

= 1.1.2 =
Cattura PHPSESSID e altri cookie HttpOnly. Aggiornamento consigliato.

= 1.1.0 =
Aggiunge scanner browser client-side: il banner ora mostra cookie reali del sito invece che lista statica.

= 1.0.0 =
Prima versione del plugin.
