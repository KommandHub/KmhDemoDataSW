# 0.9.0-beta.1

Erste Veröffentlichung.

**Katalog.** `bin/console kmh:demo-data:seed` erzeugt 2.000 Produkte, gleichmäßig
verteilt auf 40 Kategorien in fünf Verkaufskanälen — ein allgemeiner Storefront,
ein Lebensmittelmarkt, ein B2B-Großhandel, ein Beauty- und Gesundheitsshop sowie
ein Outlet. Jeder Kanal hat eine eigene Wurzelkategorie, einen eigenen
Kategoriebaum, eine eigene Landingpage und eigene Produkte. `--per-category`
ändert die Größe; ein höherer Wert ergänzt nur Produkte. Kategorielistings
verwenden das Shopware-Listing-Layout mit Filter-Sidebar, damit ein Katalog
dieser Größe durchsuchbar bleibt statt ein endloses Raster zu sein.

**Produkte.** Beschreibungen, SEO-Metadaten, Preise und Streichpreise,
Einkaufspreise, Bestand, EAN, Herstellernummern, Eigenschaften, Tags,
Kategoriezuordnungen und Sichtbarkeit je Verkaufskanal. Rund 40 % sind
Variantenartikel mit funktionierendem Konfigurator.

**Bilder.** Drei Bilder pro Produkt, passend zur jeweiligen Kategorie — rund 390
verschiedene Fotos. Das Plugin liefert Foto-Kennungen statt Fotos aus und lädt
sie beim ersten Seed herunter; ohne ausgehende Netzwerkverbindung entsteht
trotzdem ein vollständiger Katalog mit den mitgelieferten Bildern.

**Preise.** Jedes Produkt und jede Variante erhält Staffelpreise, je
Verkaufskanal über eine eigene generierte Regel. Jeder Kanal hat seinen eigenen
Preisfaktor und seine eigene Staffel: der Großhandel staffelt stark nach Menge,
das Outlet kaum. Die Regeln werden mit niedrigster Priorität angelegt, damit
vorhandene Preisregeln immer Vorrang haben.

**Bewertungen und Cross-Selling.** Unterschiedlich viele Bewertungen je Produkt
mit realistischer Notenverteilung, zum Rating passendem Text, einem Anteil in
Moderation und einem Anteil mit Shop-Antwort. Die Tabs „Ähnliche Produkte" und
„Kunden kauften auch" ziehen aus getrennten Pools und verweisen nie über den
eigenen Verkaufskanal hinaus.

**Kunden und Bestellungen.** 40 Kunden und 120 Bestellungen je Verkaufskanal,
verteilt über ein Jahr mit realistischer Mischung aus Bestell-, Zahlungs- und
Lieferstatus, damit Dashboard und Bestellliste etwas zeigen. Der Großhandel hat
eine B2B-Kundengruppe mit Nettopreisen und Geschäftskonten. Zwei Konten je Kanal
sind anmeldbar; der Befehl gibt Adressen und Passwort aus. `--skip-orders`
erzeugt nur den Katalog.

**Storefront-Seiten.** Eine Footer-Navigation für alle Verkaufskanäle, sodass
Rechts- und Serviceseiten einmal statt je Storefront gepflegt werden, sowie ein
Servicemenü mit allen erreichbaren Kanälen. Jede Footer-Seite hat Inhalt;
Rechts- und Vertragsseiten weisen ausdrücklich darauf hin, dass es sich um
Demotexte handelt, die vor dem Livegang ersetzt werden müssen.

**Beliebig oft ausführbar.** Vorhandene Daten werden vor jedem Schreibvorgang
geprüft: passende Verkaufskanäle, Kategorien, Hersteller, Eigenschaftsgruppen,
Tags und Produkte werden wiederverwendet statt dupliziert, unvollständige
ergänzt. Ein zweiter Lauf gegen eine unveränderte Installation legt nichts an
und meldet genau das. Produkte, die das Plugin nicht erzeugt hat, erhalten nie
generierte Bewertungen, Preise oder Cross-Selling. Es wird nie etwas gelöscht,
auch nicht bei der Deinstallation.

`bin/console kmh:demo-data:status` zeigt ohne Schreibzugriff, was bereits
vorhanden ist.
