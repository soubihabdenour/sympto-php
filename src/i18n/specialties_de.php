<?php
// German overrides for the 15 specialist agents.
// Only fields listed here override the English canonical record in
// src/specialties.php. The LLM system prompt continues to use the English
// version; doctors see this version in the UI.
declare(strict_types=1);

return [
    'general' => [
        'name' => 'Allgemeinmedizin-Agent',
        'specialty' => 'Allgemein- / Innere Medizin',
        'description' => 'Breite internistische Erstlinien-Argumentation über mehrere Systeme. Hilfreich, wenn die Hauptbeschwerde unspezifisch ist.',
        'required_context' => [
            'Alter und biologisches Geschlecht',
            'Hauptbeschwerde mit Beginn, Dauer und Schweregrad',
            'Vitalzeichen (RR, HF, AF, SpO2, Temperatur)',
            'Relevante medizinische und chirurgische Vorgeschichte',
            'Aktuelle Medikation und Allergien',
            'Basislabor (BB, Elektrolyte, Urinstatus), falls vorhanden',
        ],
        'common_red_flags' => [
            'Hämodynamische Instabilität (sysRR <90, HF >120, SpO2 <92 %)',
            'Bewusstseinsstörung / GCS <15',
            'Schwere unerklärte Schmerzen oder Schmerzen außer Verhältnis',
            'Rasch progrediente Symptome über Stunden bis Tage',
            'Unbeabsichtigter Gewichtsverlust >5 % in 6 Monaten',
            'Neu aufgetretenes Fieber bei immunsupprimiertem Patienten',
        ],
    ],

    'cardiology' => [
        'name' => 'Kardiologie-Agent',
        'specialty' => 'Kardiologie',
        'description' => 'Fokus auf Thoraxschmerz, Dyspnoe, Rhythmusstörungen, Herzinsuffizienz, Klappenerkrankungen und kardiovaskuläres Risiko.',
        'required_context' => [
            'Charakter, Ausstrahlung, Dauer, Auslöser und Linderung des Thoraxschmerzes',
            'Kardiovaskuläre Risikofaktoren (Hypertonie, Diabetes, Dyslipidämie, Nikotin, Familienanamnese)',
            'EKG-Befunde (Rhythmus, ST/T-Veränderungen, Achse, Intervalle)',
            'Troponin-Werte und Zeitpunkt (initial, Kontrolle)',
            'Vitalzeichen und Orthostase-Messung, falls verfügbar',
            'Vorbefunde Echo / LVEF, Z. n. Koro / Bypass / Stents',
        ],
        'common_red_flags' => [
            'ST-Hebung, neuer LSB oder posteriorer STEMI',
            'Hämodynamische Instabilität oder kardiogener Schock',
            'Reißender Thorax- oder Rückenschmerz (Aortendissektion)',
            'Belastungssynkope oder familiäre Anamnese für plötzlichen Herztod',
            'Akutes Lungenödem / Orthopnoe',
            'Neues Herzgeräusch mit Fieber (Endokarditis)',
        ],
    ],

    'neurology' => [
        'name' => 'Neurologie-Agent',
        'specialty' => 'Neurologie',
        'description' => 'Kopfschmerz, Lähmung, Krampfanfall, Sensibilitätsstörung, kognitiver Abbau, Gangstörung.',
        'required_context' => [
            'Exakte Symptom-Beginnzeit (last known well)',
            'Plötzlicher vs. gradueller Beginn und zeitlicher Verlauf',
            'Fokale vs. diffuse Defizite',
            'Hirnnerven-, Motorik-, Sensibilitäts-, Zerebellär-, Gangprüfung',
            'Kopfschmerz-Charakteristik (donnerschlagartig, schlimmster jemals, lageabhängig)',
            'Anfallssemiologie und postiktaler Zustand, falls relevant',
        ],
        'common_red_flags' => [
            'Plötzliches fokales Defizit (Schlaganfall)',
            'Donnerschlagkopfschmerz (SAB)',
            'Erstmaliger Anfall mit fokalen Zeichen oder Status epilepticus',
            'Progrediente Schwäche mit Blasen-/Mastdarm-Beteiligung (Spinalkanalstenose / Querschnitt)',
            'Fieber + Nackensteife oder Photophobie (Meningitis/Enzephalitis)',
            'Papillenödem oder neue Diplopie (erhöhter Hirndruck)',
        ],
    ],

    'dermatology' => [
        'name' => 'Dermatologie-Agent',
        'specialty' => 'Dermatologie',
        'description' => 'Hautläsionen, Exantheme, pigmentierte Läsionen, Haar- und Nagelerkrankungen.',
        'required_context' => [
            'Läsionsmorphologie (Makula, Papel, Plaque, Vesikel, Pustel, Knoten etc.)',
            'Verteilung, Konfiguration und befallene Körperstellen',
            'Zeitverlauf (Beginn, Entwicklung, frühere ähnliche Episoden)',
            'Symptome (Juckreiz, Schmerz, Blutung, systemische Zeichen)',
            'Foto oder detaillierte visuelle Beschreibung / Dermatoskopie, falls vorhanden',
            'Persönliche/familiäre Hauttumor-Anamnese und Atopie',
        ],
        'common_red_flags' => [
            'ABCDE-positive pigmentierte Läsion oder rasch verändernder Nävus',
            'Schleimhautbeteiligung (SJS/TEN)',
            'Rasch ausbreitendes Erythem mit Schmerz außer Verhältnis (nekrotisierende Fasziitis)',
            'Nicht wegdrückbare Purpura (Vaskulitis, Meningokokkämie)',
            'Hautversagen: konfluente Erythrodermie >90 % KOF',
            'Neue bullöse Eruption mit Fieber und Hautempfindlichkeit',
        ],
    ],

    'pediatrics' => [
        'name' => 'Pädiatrie-Agent',
        'specialty' => 'Pädiatrie',
        'description' => 'Kinder und Jugendliche. Differenzialdiagnose und Dosierung an Alter und Gewicht angepasst.',
        'required_context' => [
            'Exaktes Alter (Monate/Jahre) und Gewicht (kg)',
            'Geburtsanamnese bei Säuglingen (Gestationsalter, Komplikationen)',
            'Impfstatus (aktueller Stand laut lokalem Schema?)',
            'Ernährung und Gedeihen / Wachstumskurve',
            'Entwicklungsmeilensteine entsprechend dem Alter',
            'Expositionen im Haushalt und Familie',
        ],
        'common_red_flags' => [
            'Toxisch wirkender Säugling (Pediatric Assessment Triangle pathologisch)',
            'Petechiales Exanthem mit Fieber',
            'Galliges Erbrechen beim Neugeborenen (Malrotation/Volvulus)',
            'Untröstliches Schreien mit Peritonismus (Invagination)',
            'Verletzungsanamnese inkonsistent (Kindesmisshandlung)',
            'Apparent Life-Threatening Event (ALTE / BRUE) bei <1 Jahr',
        ],
    ],

    'oncology' => [
        'name' => 'Onkologie-Agent',
        'specialty' => 'Medizinische Onkologie',
        'description' => 'Krebsdiagnose, Staging-Überlegungen, Therapiestrategien, onkologische Notfälle.',
        'required_context' => [
            'Histologie / Biopsie-Ergebnis und Grading',
            'Stadium (TNM, mit Bildgebungsgrundlage)',
            'Allgemeinzustand (ECOG 0–4 oder KPS)',
            'Molekular- / Biomarker-Status (z. B. EGFR, ALK, HER2, MSI, PD-L1, BRCA)',
            'Vorangegangene systemische Therapien und Ansprechen',
            'Begleiterkrankungen mit Einfluss auf die Therapieverträglichkeit (kardial, renal, hepatisch)',
        ],
        'common_red_flags' => [
            'Neutropenisches Fieber (ANC <500 oder erwarteter Abfall, T ≥38,3 °C)',
            'Spinale Kompression (Rückenschmerz + neurol. Defizit)',
            'Tumorhyperkalzämie (Ca >12 mg/dL mit Symptomen)',
            'V.-cava-superior-Syndrom (Gesichts-/Armödem, Plethora)',
            'Tumorlysesyndrom (HyperK, HyperP, HypoCa, Hyperurikämie, ANV)',
            'Hyperviskosität (Sehstörungen, Schleimhautblutungen, neurol. Zeichen)',
        ],
    ],

    'radiology' => [
        'name' => 'Radiologie-Agent',
        'specialty' => 'Diagnostische Radiologie',
        'description' => 'Hilft bei der Interpretation radiologischer Befunde und empfiehlt weiterführende Bildgebung.',
        'required_context' => [
            'Modalität (CT, MRT, Sono, Röntgen, PET, Mammographie)',
            'Körperregion und Untersuchungsprotokoll',
            'Klinische Fragestellung der Bildgebung',
            'Voraufnahmen zum Vergleich verfügbar',
            'Kontrastmittel-Einsatz, Kontraindikationen, frühere Reaktionen',
            'Nierenfunktion (eGFR) bei geplantem i. v.-Kontrastmittel',
        ],
        'common_red_flags' => [
            'Befunde mit Malignitätsverdacht (Raumforderung, Lymphadenopathie, Metastasen)',
            'Akute vaskuläre Notfälle (Dissektion, große LE, Aneurysma-Ruptur)',
            'Akute Blutung (intrakraniell, intraabdominell, retroperitoneal)',
            'Darmperforation oder Ischämie',
            'Spinale Kompression in der Bildgebung',
            'Spannungspneumothorax',
        ],
    ],

    'emergency' => [
        'name' => 'Notfallmedizin-Agent',
        'specialty' => 'Notfallmedizin',
        'description' => 'Zeitkritische Reanimation, undifferenzierte Beschwerden, Disposition-Entscheidungen.',
        'required_context' => [
            'Vitalzeichen und Verlauf (Ankunft vs. aktuell)',
            'Bewusstseinslage (GCS, Wachheit)',
            'Symptombeginn (last known well)',
            'Kurze fokussierte Anamnese (SAMPLE / AMPLE)',
            'Bedside-EKG, BZ-Stix, Schwangerschaftstest, wenn relevant',
            'Point-of-Care-Ultraschall-Befunde, falls durchgeführt',
        ],
        'common_red_flags' => [
            'Atemwegskompromittierung / Stridor / Verlust der Schutzreflexe',
            'Schock jeglicher Ätiologie (sysRR <90 oder Schockindex >1)',
            'GCS <15 oder rasche Verschlechterung',
            'Sepsis mit Organdysfunktion',
            'Aktivierungskriterien erfüllt: STEMI / Schlaganfall / Sepsis / Trauma',
            'Anaphylaxie mit Atemwegs- oder hämodynamischer Beteiligung',
        ],
    ],

    'infectious' => [
        'name' => 'Infektiologie-Agent',
        'specialty' => 'Infektiologie',
        'description' => 'Sepsis, Fieber unbekannter Ursache, Antibiotikawahl, Reise- und Expositionsanamnese.',
        'required_context' => [
            'Fieberverlauf, Dauer und begleitende lokalisierende Symptome',
            'Reiseanamnese (Regionen, Zeitraum, Expositionen)',
            'Tier- / Insekten- / berufliche / sexuelle Expositionen',
            'Immunstatus (HIV, Transplantation, Neutropenie, Biologika, Asplenie)',
            'Kürzliche Antibiose und bekannte Kolonisation (MRSA, ESBL, VRE)',
            'Kulturen (Blut, Urin, lokal) und Bildgebung, falls verfügbar',
        ],
        'common_red_flags' => [
            'Septischer Schock / Laktat >2 mit Hypotonie',
            'Nekrotisierende Weichteilinfektion (Schmerz außer Verhältnis, Krepitation)',
            'Meningitis / Enzephalitis (Fieber + neurol. Zeichen)',
            'Endokarditis mit embolischen Phänomenen',
            'Neutropenisches Fieber',
            'Postsplenektomie-Fieber (OPSI-Risiko)',
        ],
    ],

    'psychiatry' => [
        'name' => 'Psychiatrie-Agent',
        'specialty' => 'Psychiatrie',
        'description' => 'Affektive, Angst-, psychotische, Sucht- und akute Verhaltensstörungen.',
        'required_context' => [
            'Stimmung, Schlaf, Appetit, Antrieb, Psychomotorik, Anhedonie',
            'Suizidale / homizidale Ideation, Plan, Intention, Zugriff auf Mittel, Vorversuche',
            'Substanzkonsum (Alkohol, Opioide, Stimulanzien, Benzodiazepine) — Menge und Zeitpunkt',
            'Psychiatrische Vorgeschichte, frühere Hospitalisierungen, aktuelle Medikation',
            'Aktuelles Labor (TSH, BB, Elektrolyte, B12, Urin-Tox) zum Ausschluss organischer Ursachen',
            'Psychosoziale Unterstützung und Sicherheit des aktuellen Umfelds',
        ],
        'common_red_flags' => [
            'Aktive Suizid-/Homizidabsicht mit Plan und Mitteln (C-SSRS hoch)',
            'Akute Psychose mit imperativen Halluzinationen oder schwerer Desorganisation',
            'Katatonie (Rigidität, Mutismus, Posturieren)',
            'Serotoninsyndrom / MNS (Hyperthermie + Rigor + vegetative Instabilität)',
            'Delir fälschlich als primär psychiatrische Erkrankung eingestuft',
            'Alkohol-/Benzodiazepin-Entzug mit Krampfanfall oder vegetativer Instabilität',
        ],
    ],

    'endocrinology' => [
        'name' => 'Endokrinologie-Agent',
        'specialty' => 'Endokrinologie',
        'description' => 'Diabetes, Schilddrüse, Nebenniere, Hypophyse, Kalzium-/Knochen- und Stoffwechselstörungen.',
        'required_context' => [
            'Hormonlabor MIT Einheiten UND Referenzbereich',
            'Begleitmedikation (Steroide, Hormone, Metformin, Lithium, Amiodaron, Kontrastmittel)',
            'Schwangerschaftsstatus, falls zutreffend',
            'Symptombeginn, Schweregrad und Chronologie',
            'Familienanamnese für Endokrinopathien / MEN-Syndrome',
        ],
        'common_red_flags' => [
            'DKA (Glukose >250, Anionenlücke, Ketone) / HHS (Glukose >600, hyperosmolar)',
            'Addison-Krise (Hypotonie + Hyponatriämie + Hyperkaliämie bei steroidpflichtigem Patienten)',
            'Thyreotoxische Krise oder Myxödemkoma',
            'Schwere Hyponatriämie <120 mit neurol. Symptomen',
            'Hyperkalzämische Krise (>14 mg/dL mit Bewusstseinsstörung)',
            'Phäochromozytom-Krise (paroxysmale Hypertonie mit Kopfschmerz, Palpitationen, Schwitzen)',
        ],
    ],

    'gastro' => [
        'name' => 'Gastroenterologie-Agent',
        'specialty' => 'Gastroenterologie / Hepatologie',
        'description' => 'Bauchschmerz, GI-Blutung, CED, Lebererkrankungen, pankreato-biliäre Erkrankungen.',
        'required_context' => [
            'Schmerzlokalisation, Charakter, Ausstrahlung, Auslöser, Linderung',
            'Stuhlveränderungen (Farbe, Blut, Frequenz, Meläna vs. Hämatochezie)',
            'Alkoholmenge und Chronizität',
            'Hepatitis-Risiko und Impfstatus',
            'Leberwerte, Lipase, Laktat, Gerinnung, falls relevant',
            'Bildgebung (Sono, CT, MRCP) verfügbar',
        ],
        'common_red_flags' => [
            'Hämatemesis mit hämodynamischer Instabilität (massive OGIB)',
            'Peritonitis (Abwehrspannung, Loslassschmerz, Rigidität)',
            'Aszendierende Cholangitis (Charcot-Trias / Reynolds-Pentade)',
            'Akutes Leberversagen mit Enzephalopathie und Koagulopathie',
            'Mesenteriale Ischämie (Schmerz außer Verhältnis + Azidose + Laktat erhöht)',
            'Toxisches Megakolon bei CED',
        ],
    ],

    'pulmonology' => [
        'name' => 'Pneumologie-Agent',
        'specialty' => 'Pneumologie',
        'description' => 'Husten, Dyspnoe, Giemen, Hämoptyse, interstitielle Lungenerkrankungen, schlafbezogene Atemstörungen.',
        'required_context' => [
            'SpO2 an Raumluft UND unter Sauerstoff, falls genutzt',
            'Atemfrequenz, Atemarbeit, Fähigkeit ganze Sätze zu sprechen',
            'Pack-years und berufliche Expositionen',
            'Lungenfunktion (FEV1, FVC, FEV1/FVC, DLCO), falls vorhanden',
            'Bildgebung (Röntgen-Thorax, CT-Thorax, CTPA) Befunde',
            'Schlafsymptome (Schnarchen, beobachtete Apnoen, Tagesmüdigkeit), wenn relevant',
        ],
        'common_red_flags' => [
            'Hypoxämische respiratorische Insuffizienz (SpO2 <90 % an Raumluft oder PaO2 <60)',
            'Massive Hämoptyse (>200 ml oder mit Atemwegskompromittierung)',
            'Spannungspneumothorax (Trachealverschiebung, hämodynamische Instabilität)',
            'LE mit RV-Belastung / hämodynamischer Instabilität (sub-massiv / massiv)',
            'Schwerer Asthmaanfall ohne Bronchodilatator-Ansprechen (stille Lunge, Erschöpfung)',
            'Neuer Sauerstoffbedarf bei ILD (akute Exazerbation)',
        ],
    ],

    'nephrology' => [
        'name' => 'Nephrologie-Agent',
        'specialty' => 'Nephrologie',
        'description' => 'ANV, CKD, Elektrolyt- und Säure-Base-Störungen, Hypertonie, glomeruläre Erkrankungen.',
        'required_context' => [
            'Ausgangs-Kreatinin UND aktueller Wert (mit Zeitpunkt)',
            'Volumenstatus (Orthostase, JVP, Ödeme, Gewichtsverlauf, Urinausscheidung)',
            'Urinstatus mit Mikroskopie UND Urin-Elektrolyte / Urin-Kreatinin',
            'Medikamenten-Review (NSAR, ACE-H/AT1-B, Kontrastmittel, Aminoglykoside, Vancomycin, PPI)',
            'Aktuelle Bildgebung bei Verdacht auf Obstruktion (Nieren-Sono erstrangig)',
            'Hinweise auf Sepsis / Hypotonie / Herzinsuffizienz',
        ],
        'common_red_flags' => [
            'Schwere Hyperkaliämie (K >6,5 oder EKG-Veränderungen)',
            'Anurie mit steigendem Kreatinin (Obstruktion oder vaskuläre Katastrophe)',
            'Lungenödem bei oligurischem / dialysepflichtigem Patient',
            'Aktives Urinsediment (Erythrozytenzylinder, dysmorphe Erythrozyten) mit steigendem Kreatinin — RPGN',
            'Schwere Hyponatriämie <120 mit neurol. Symptomen',
            'Urämische Enzephalopathie / Perikarditis',
        ],
    ],

    'obgyn' => [
        'name' => 'Gynäkologie/Geburtshilfe-Agent',
        'specialty' => 'Geburtshilfe und Gynäkologie',
        'description' => 'Schwangerschaft, gynäkologische Beschwerden, Kontrazeption, Zyklusstörungen, gynäkologische Onkologie.',
        'required_context' => [
            'LMP-Datum und Schwangerschaftsstatus (qualitatives UND quantitatives β-hCG)',
            'Gravidität / Parität (G_P_)',
            'Blutungsmuster, Schweregrad, Koagel / Gewebe',
            'Schmerzcharakter, Lokalisation und Ausstrahlung',
            'Sexual-, Kontrazeptions- und STI-Anamnese, falls relevant',
            'Bei Schwangerschaft: Gestationsalter, frühere Komplikationen, Rh-Status, RR-Verlauf',
        ],
        'common_red_flags' => [
            'Rupturierte Extrauteringravidität mit hämodynamischer Instabilität',
            'Schwere Präeklampsie / Eklampsie / HELLP (RR ≥160/110, Proteinurie, Endorganschäden)',
            'Postpartale Blutung (>500 ml vaginal oder >1000 ml Sectio)',
            'Adnextorsion (plötzlicher schwerer einseitiger Schmerz ± Übelkeit)',
            'Septischer Abort (Fieber + übel riechender Fluor + Retention)',
            'Postmenopausale Blutung (bis Endometriumkarzinom ausgeschlossen)',
        ],
    ],
];
