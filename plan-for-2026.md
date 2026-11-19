# Plan for LTS 2026

Nuvarande LTS-head: `312d9bc`. Aktuell pluginversion: `2.2.0`. Jämförelseyta för
2026: Municipio LTS 2026-stacken, Matomo, ACF,
`helsingborg-stad/broken-link-detector`/Whitespace-forken och eventuella
redaktionella dashboard-/statistikytor i temat eller andra LTS-plugins.

## Slutsats

Content Insights for Editors är ett eget LTS-/Whitespace-paket snarare än en
Helsingborg-fork. Det bör därför inte rebasas mot en ny upstreambas, utan
verifieras som en redaktionell stödfunktion i den nya LTS-stacken.

Första passet pekar på att pluginen fortfarande kan ha ett tydligt värde:
redaktörer får Matomo-data, brutna länkar, kundfeedback och notifieringar på
samma ställe. De största 2026-frågorna är beroendet till broken-link-detector,
säkerheten i admin/AJAX-flöden, ACF-fälten och om Matomo-ansvaret ska vara
separerat från Tracking GDPR.

## Arbetsplan

- [ ] Bekräfta om paketet fortfarande ska ingå i LTS 2026 som redaktionellt
      verktyg.
- [ ] Verifiera Composer-beroendet mot broken-link-detector och om
      Whitespace-forken fortfarande är rätt källa.
- [ ] Gå igenom AJAX-, cron- och e-postflöden med fokus på capability checks,
      nonce och felhantering.
- [ ] Verifiera Matomo API-inställningarna mot 2026-miljöernas Tracking
      GDPR-/Matomo-konfiguration.
- [ ] Kontrollera att ACF-fält, äldre ACF post type field-plugin och
      `WP_List_Table`-ytan fungerar med aktuell WordPress/ACF-version.
- [ ] Behåll byggda assets och språkfiler orörda tills implementationen kräver
      ordinarie bygg- och i18n-steg.

## Beslutstabell

| Område                         | Vår slutändring                                                                                             | 2026-läge                                                                             | Bedömning                                                               |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Paketering                     | Composerpaket `municipio/content-insights-for-editors` med AGPL och VCS-beroende till broken-link-detector. | Eget paket utan tydlig Helsingborg-upstream.                                          | Behåll om funktionen ingår i LTS; verifiera beroendekällan.             |
| Broken links                   | Integrerar mot `\BrokenLinkDetector\App`, visar listor, dashboardwidgetar och metaboxar.                    | Ingen motsvarande yta hittad i snabbpasset.                                           | Behåll kandidat; verifiera pluginets databas- och API-kontrakt.         |
| Matomo-insikter                | Hämtar Matomo-data via API-token och bygger redaktionella listor.                                           | Tracking GDPR ansvarar för Matomo-spårning och samtycke, inte redaktionell statistik. | Behåll som separat ansvar om redaktörsrapporteringen används.           |
| Kundfeedback                   | Läser data från optional `CustomerFeedback`-plugin och lägger till dashboard-/mailsektioner.                | Oklart om kundfeedback ingår i 2026-bundlet.                                          | Verifiera manuellt; släpp integrationen om beroendet inte längre finns. |
| Notifieringsmail               | Schemalagt `cife_cron_mail`, mailtemplates och filter för logga, sektioner och variabler.                   | Ingen upstream-motsvarighet hittad.                                                   | Behåll kandidat; säkerhets- och leveransgranska.                        |
| Admin UI                       | Adminmenyer, dashboardwidgetar, listtabeller och metaboxar för redaktörer.                                  | Kan påverkas av aktuell WordPress-admin och editorroller.                             | Behåll smalt; verifiera capability-modellen.                            |
| ACF och äldre fältplugin       | Laddar ACF options page och bundlad ACF post type field-plugin om funktionen saknas.                        | ACF 6.x bör verifieras; äldre bundlad fältplugin kan vara teknisk skuld.              | Verifiera manuellt; ersätt med modern ACF-lösning om möjligt.           |
| Säkerhet                       | AJAX-trigger, externa API-tokenvärden, direkta SQL-frågor och mailutskick är känsliga ytor.                 | Inget 2026-paket ersätter detta ansvar.                                               | Återskapa smalare vid behov; gör säkerhetsgranskning först.             |
| Assets, språk och releasefiler | Har CSS/JS, bilder och språkfiler i repo.                                                                   | Ska inte uppdateras i snabbpasset.                                                    | Ej relevant för planfilen.                                              |

## Rekommenderad rebase-plan

1. Ta ett produktbeslut om paketet ska ingå i LTS 2026.
2. Om ja: börja med Composer och broken-link-detector-källan.
3. Säkerhetsgranska admin/AJAX/cron innan funktionell portning.
4. Verifiera Matomo-ansvar mot Tracking GDPR så att API-statistik och
   besökarsamtycke inte blandas ihop.
5. Testa redaktörsflödet i wp-admin: dashboard, listvy, metabox och mail.

## Risker att verifiera

- AJAX- och cronflöden kan sakna dagens förväntade nonce-/capability-skydd.
- Matomo API-token och externa API-fel behöver hanteras säkert.
- Direkt SQL mot broken-link- och statistikdata kan påverkas av schemaändringar.
- Äldre ACF field plugin kan vara inkompatibel med aktuell ACF.
- Kundfeedbackintegrationens beroende kan saknas i 2026-stacken.

## Kommandon körda

- `git status --short`
- `git rev-parse --short HEAD`
- `rg --files`
- `sed` av README, Composer och pluginbootstrap.
- Riktade `rg`-sökningar efter hooks, cron, AJAX, ACF, Matomo, broken links och
  kundfeedback.
