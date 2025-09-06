# Postpartner Suche (MyBB Plugin)
Mit diesem Plugin können Mitglieder in ihrem Benutzerkontrollzentrum (UCP) angeben, ob sie aktuell nach einem Postpartner suchen.  
Die Suchenden werden an verschiedenen Stellen im Forum hervorgehoben und auf einer Übersichtsseite gesammelt dargestellt.  
Optional kann zusätzlich eine Benachrichtigung in Discord über einen Webhook erstellt werden.

## Features
- Benutzer können im **UCP** angeben, dass sie einen Postpartner suchen
- Anzeige eines zufälligen Suchenden im **Header**
- Übersicht aller Suchenden auf einer **separaten Seite**
- Einbindung in das **Benutzerprofil**
- Optional: **Discord-Benachrichtigung** über einen Webhook, wenn ein Nutzer einen Postpartner sucht
- Verwaltung über die MyBB-Einstellungen (Aktivierung, Optionen)

## Installation
1. Lade die Datei `postpartner.php` in den Ordner inc/plugins/ deines MyBB-Forums hoch.
2. Aktiviere das Plugin im **Admin Control Panel** 
3. Nimm die notwendigen **Einstellungen** vor

## Nutzung
- Nutzer finden im **Benutzerkontrollzentrum (UCP)** einen neuen Bereich „Postpartner“,  
in dem sie die Suche aktivieren können. Dort werden auch alle Suchenden aufgelistet.
- Im **Header** wird zufällig ein suchendes Mitglied angezeigt.
- Im **Forumbit** z.B. vor dem Ingame in Index, kann zufällig ein Suchender ausgegeben werden.
- Alle aktiven Suchenden sind auf einer separaten Übersichtsseite einsehbar.
- Falls konfiguriert, wird beim Start einer Suche automatisch ein Post in **Discord** via Webhook erstellt.


