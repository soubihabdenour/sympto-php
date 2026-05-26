<?php
// French overrides for the 15 specialist agents.
// Only fields listed here override the English canonical record in
// src/specialties.php. The LLM system prompt continues to use the English
// version; doctors see this version in the UI.
declare(strict_types=1);

return [
    'general' => [
        'name' => 'Agent de médecine générale',
        'specialty' => 'Médecine générale / Soins primaires',
        'description' => "Raisonnement de soins primaires de première ligne, large et multi-systémique. Utile lorsque le motif de consultation est non spécifique ou ambulatoire.",
        'required_context' => [
            'Âge et sexe biologique',
            'Motif de consultation : début, durée, sévérité',
            'Signes vitaux (TA, FC, FR, SpO2, température)',
            'Antécédents médicaux et chirurgicaux pertinents',
            'Traitements en cours et allergies',
            'Bilan biologique de base (NFS, ionogramme, BU) si disponible',
        ],
        'common_red_flags' => [
            'Instabilité hémodynamique (PAS <90, FC >120, SpO2 <92 %)',
            "Altération de l'état de conscience / GCS <15",
            'Douleur sévère inexpliquée ou disproportionnée',
            'Symptômes rapidement progressifs sur heures à jours',
            'Perte de poids involontaire >5 % en 6 mois',
            'Fièvre récente chez un patient immunodéprimé',
        ],
    ],

    'internal-medicine' => [
        'name' => 'Agent de médecine interne',
        'specialty' => 'Médecine interne',
        'description' => "Médecine interne hospitalière et ambulatoire chez l'adulte, centrée sur la polymorbidité, la prise en charge des maladies chroniques, la révision des polymédications et le raisonnement hospitalier multi-systémique.",
        'required_context' => [
            "Âge, sexe biologique, fragilité / statut fonctionnel (ECOG, Barthel)",
            "Liste des problèmes actifs et comorbidités chroniques (HTA, diabète, IRC, IC, BPCO, etc.)",
            "Liste complète des médicaments y compris OTC, suppléments, antibiothérapie récente",
            "Signes vitaux (TA, FC, FR, SpO2, température) avec tendance si hospitalisé",
            "Bilans biologiques actuel et de référence : NFS, ionogramme, bilan hépatique, INR, troponine/BNP, lactate, GDS si aigu",
            "Allergies et antécédents d'effets indésirables médicamenteux",
            "Directives anticipées / niveau de soins en cas de problématique de fin de vie",
        ],
        'common_red_flags' => [
            "Sepsis : qSOFA ≥2, lactate ≥2 ou dysfonction d'organe avec infection",
            "Instabilité hémodynamique (PAS <90, index de choc >1, signes d'hypoperfusion)",
            "Insuffisance respiratoire aiguë (SpO2 <90 % en AA, PaCO2 ≥50 avec acidose)",
            "Trouble électrolytique sévère (K >6 ou <2.5, Na <120 ou >160, Ca >13)",
            "IRA KDIGO ≥2 avec anurie ou surcharge volémique",
            "Hémorragie digestive avec retentissement hémodynamique ou chute Hb ≥2 g/dL",
            "Déficit neurologique focal nouveau ou GCS <15 persistant (confusion vs AVC)",
            "Interaction médicamenteuse à risque (QT long, syndrome sérotoninergique, IRA aux AINS)",
        ],
    ],

    'cardiology' => [
        'name' => 'Agent de cardiologie',
        'specialty' => 'Cardiologie',
        'description' => "Centré sur la douleur thoracique, la dyspnée, les troubles du rythme, l'insuffisance cardiaque, les valvulopathies et le risque cardiovasculaire.",
        'required_context' => [
            'Caractère, irradiation, durée, facteurs déclenchants et soulageants de la douleur thoracique',
            'Facteurs de risque cardiovasculaire (HTA, diabète, dyslipidémie, tabac, antécédents familiaux)',
            'ECG (rythme, modifications du ST/T, axe, intervalles)',
            'Troponine(s) avec timing (initiale, contrôle)',
            "Signes vitaux et mesures orthostatiques si disponibles",
            'Échocardiographie antérieure / FEVG, coronarographie / pontage / stents',
        ],
        'common_red_flags' => [
            'Sus-décalage du ST, BBG nouveau ou STEMI postérieur',
            'Instabilité hémodynamique ou choc cardiogénique',
            'Douleur thoracique ou dorsale en coup de poignard (dissection)',
            "Syncope à l'effort ou antécédent familial de mort subite",
            'Œdème aigu du poumon / orthopnée',
            "Souffle cardiaque nouveau avec fièvre (endocardite)",
        ],
    ],

    'neurology' => [
        'name' => 'Agent de neurologie',
        'specialty' => 'Neurologie',
        'description' => "Céphalées, déficit moteur, crise comitiale, troubles sensitifs, déclin cognitif, troubles de la marche.",
        'required_context' => [
            'Heure précise de début (dernier état normal connu)',
            'Mode de début (brutal ou progressif) et évolution',
            'Déficits focaux versus diffus',
            'Examen des paires crâniennes, moteur, sensitif, cérébelleux, marche',
            "Caractéristiques de la céphalée (en coup de tonnerre, la pire de la vie, posturale)",
            'Sémiologie de la crise et état post-critique le cas échéant',
        ],
        'common_red_flags' => [
            'Déficit focal de survenue brutale (AVC)',
            'Céphalée en coup de tonnerre (HSA)',
            "Première crise avec signes focaux ou état de mal",
            'Déficit moteur progressif avec atteinte sphinctérienne (compression médullaire)',
            'Fièvre + raideur de nuque ou photophobie (méningite/encéphalite)',
            'Œdème papillaire ou diplopie nouvelle (HTIC)',
        ],
    ],

    'dermatology' => [
        'name' => 'Agent de dermatologie',
        'specialty' => 'Dermatologie',
        'description' => "Lésions cutanées, éruptions, lésions pigmentées, atteintes des phanères.",
        'required_context' => [
            'Morphologie lésionnelle (macule, papule, plaque, vésicule, pustule, nodule, etc.)',
            'Distribution, configuration, sites atteints',
            'Chronologie (début, évolution, épisodes antérieurs)',
            'Symptômes (prurit, douleur, saignement, signes systémiques)',
            'Photo ou description visuelle détaillée / dermoscopie si disponibles',
            'Antécédents personnels et familiaux de cancer cutané et atopie',
        ],
        'common_red_flags' => [
            'Lésion pigmentée ABCDE-positive ou nævus à évolution rapide',
            'Atteinte muqueuse (SJS/NET)',
            'Érythème en propagation rapide avec douleur disproportionnée (fasciite nécrosante)',
            'Purpura non vasculaire (vascularite, méningococcémie)',
            "Insuffisance cutanée : érythrodermie confluente >90 % de la surface corporelle",
            "Éruption bulleuse nouvelle avec fièvre et sensibilité cutanée",
        ],
    ],

    'pediatrics' => [
        'name' => 'Agent de pédiatrie',
        'specialty' => 'Pédiatrie',
        'description' => "Enfants et adolescents. Diagnostic différentiel et posologies adaptés à l'âge et au poids.",
        'required_context' => [
            'Âge exact (mois/années) et poids (kg)',
            'Anamnèse périnatale chez le nourrisson (terme, complications)',
            'Statut vaccinal (à jour selon le calendrier local ?)',
            'Mode alimentaire et trajectoire de croissance',
            "Acquisitions du développement pour l'âge",
            "Expositions de l'entourage et de la fratrie",
        ],
        'common_red_flags' => [
            "Nourrisson d'aspect toxique (Triangle d'évaluation pédiatrique anormal)",
            'Éruption pétéchiale avec fièvre',
            'Vomissements bilieux chez le nouveau-né (malrotation/volvulus)',
            'Pleurs inconsolables avec défense abdominale (invagination)',
            'Histoire de la blessure incompatible (maltraitance)',
            'Malaise grave du nourrisson (ALTE / BRUE) chez le <1 an',
        ],
    ],

    'oncology' => [
        'name' => "Agent d'oncologie",
        'specialty' => 'Oncologie médicale',
        'description' => "Diagnostic du cancer, bilan d'extension, schémas thérapeutiques, urgences oncologiques.",
        'required_context' => [
            'Histologie / résultat de biopsie et grade',
            "Stade (TNM, avec base d'imagerie)",
            'Indice de performance (ECOG 0–4 ou KPS)',
            'Statut moléculaire / biomarqueurs (EGFR, ALK, HER2, MSI, PD-L1, BRCA…)',
            'Traitements systémiques antérieurs et réponse',
            'Comorbidités pertinentes pour la tolérance (cardiaque, rénale, hépatique)',
        ],
        'common_red_flags' => [
            "Neutropénie fébrile (PNN <500 ou en chute attendue, T ≥38,3 °C)",
            'Compression médullaire (dorsalgies + déficit neurologique)',
            "Hypercalcémie maligne (calcémie >12 mg/dL avec symptômes)",
            "Syndrome cave supérieur (œdème facial / des membres supérieurs, érythrose)",
            'Syndrome de lyse tumorale (hyperK, hyperP, hypoCa, hyperuricémie, IRA)',
            "Hyperviscosité (troubles visuels, saignements muqueux, signes neuro)",
        ],
    ],

    'radiology' => [
        'name' => 'Agent de radiologie',
        'specialty' => 'Radiologie diagnostique',
        'description' => "Aide à l'interprétation des comptes-rendus d'imagerie et propose les examens d'imagerie suivants.",
        'required_context' => [
            "Modalité (TDM, IRM, échographie, radiographie, TEP, mammographie)",
            "Région anatomique et protocole utilisé",
            "Indication clinique de l'examen",
            "Imagerie antérieure pour comparaison",
            "Utilisation du produit de contraste, contre-indications, antécédents de réaction",
            'Fonction rénale (DFG) si contraste IV envisagé',
        ],
        'common_red_flags' => [
            "Lésions évoquant une malignité (masse, adénopathie, métastase)",
            "Urgences vasculaires aiguës (dissection, EP massive, rupture d'anévrysme)",
            "Hémorragie aiguë (intracrânienne, intra-abdominale, rétropéritonéale)",
            "Perforation ou ischémie digestive",
            "Compression médullaire à l'imagerie",
            "Pneumothorax compressif",
        ],
    ],

    'emergency' => [
        'name' => "Agent de médecine d'urgence",
        'specialty' => "Médecine d'urgence",
        'description' => "Réanimation temps-critique, présentations indifférenciées, décisions d'orientation.",
        'required_context' => [
            "Signes vitaux et leur tendance (admission vs actuels)",
            'État de conscience (GCS, vigilance)',
            "Heure de début des symptômes (dernier état normal connu)",
            'Anamnèse brève et ciblée (SAMPLE / AMPLE)',
            'ECG, glycémie capillaire, test de grossesse au lit du malade le cas échéant',
            "Résultats de l'échographie au lit du malade si réalisée",
        ],
        'common_red_flags' => [
            "Atteinte des voies aériennes / stridor / perte des réflexes",
            "État de choc quelle qu'en soit l'origine (PAS <90 ou indice de choc >1)",
            'GCS <15 ou détérioration rapide',
            "Sepsis avec dysfonction d'organe",
            "Critères d'activation : STEMI / AVC / sepsis / trauma",
            "Anaphylaxie avec atteinte respiratoire ou hémodynamique",
        ],
    ],

    'infectious' => [
        'name' => "Agent d'infectiologie",
        'specialty' => 'Maladies infectieuses',
        'description' => "Sepsis, fièvre inexpliquée, choix d'antibiothérapie, anamnèse de voyage et d'exposition.",
        'required_context' => [
            'Profil de la fièvre, durée, signes localisateurs associés',
            "Anamnèse de voyage (régions, dates, expositions)",
            'Expositions animales / vectorielles / professionnelles / sexuelles',
            "Statut immunitaire (VIH, transplantation, neutropénie, biothérapies, asplénie)",
            'Antibiothérapie récente et colonisation connue (SARM, BLSE, ERV)',
            'Cultures (hémocultures, urines, site spécifique) et imagerie si disponibles',
        ],
        'common_red_flags' => [
            'Choc septique / lactates >2 avec hypotension',
            'Infection nécrosante des tissus mous (douleur disproportionnée, crépitation)',
            'Méningite / encéphalite (fièvre + signes neuro)',
            'Endocardite avec phénomènes emboliques',
            'Neutropénie fébrile',
            'Fièvre post-splénectomie (risque OPSI)',
        ],
    ],

    'psychiatry' => [
        'name' => 'Agent de psychiatrie',
        'specialty' => 'Psychiatrie',
        'description' => "Troubles de l'humeur, anxieux, psychotiques, addictions et présentations comportementales aiguës.",
        'required_context' => [
            'Humeur, sommeil, appétit, énergie, troubles psychomoteurs, anhédonie',
            "Idéations suicidaires / homicidaires, plan, intention, accès aux moyens, tentatives antérieures",
            "Consommation de substances (alcool, opioïdes, stimulants, benzodiazépines) — quantité et récence",
            'Antécédents psychiatriques, hospitalisations antérieures, traitements actuels',
            "Bilan médical récent (TSH, NFS, ionogramme, B12, tox urinaire) pour exclure une cause organique",
            "Soutien psychosocial et sécurité de l'environnement actuel",
        ],
        'common_red_flags' => [
            'Idéations suicidaires/homicidaires actives avec plan et moyens (C-SSRS élevé)',
            "Psychose aiguë avec hallucinations injonctives ou comportement grossièrement désorganisé",
            "Catatonie (rigidité, mutisme, posturage)",
            "Syndrome sérotoninergique / SMN (hyperthermie + rigidité + instabilité neurovégétative)",
            "Confusion (delirium) prise à tort pour une pathologie psychiatrique",
            "Sevrage alcoolique / benzodiazépines avec crise ou instabilité neurovégétative",
        ],
    ],

    'endocrinology' => [
        'name' => "Agent d'endocrinologie",
        'specialty' => 'Endocrinologie',
        'description' => "Diabète, thyroïde, surrénales, hypophyse, calcium/os et troubles métaboliques.",
        'required_context' => [
            'Bilans hormonaux AVEC unités ET intervalles de référence',
            "Traitements concomitants (corticoïdes, hormones, metformine, lithium, amiodarone, contraste)",
            'Statut de grossesse si applicable',
            "Début, sévérité et chronologie des symptômes",
            "Antécédents familiaux d'endocrinopathie / syndromes MEN",
        ],
        'common_red_flags' => [
            "Acidocétose diabétique (glycémie >250, trou anionique, cétones) / SHH (glycémie >600, hyperosmolarité)",
            "Insuffisance surrénale aiguë (hypotension + hyponatrémie + hyperkaliémie sous corticothérapie)",
            "Crise thyréotoxique ou coma myxœdémateux",
            "Hyponatrémie sévère <120 avec signes neurologiques",
            "Crise hypercalcémique (>14 mg/dL avec troubles de la conscience)",
            "Crise de phéochromocytome (HTA paroxystique avec céphalée, palpitations, sueurs)",
        ],
    ],

    'gastro' => [
        'name' => "Agent de gastro-entérologie",
        'specialty' => 'Gastro-entérologie / Hépatologie',
        'description' => "Douleur abdominale, hémorragie digestive, MICI, hépatopathies, pathologies pancréato-biliaires.",
        'required_context' => [
            "Topographie, caractère, irradiation, déclencheurs et soulagement de la douleur",
            "Modifications du transit (couleur, sang, fréquence, méléna vs hématochézie)",
            "Consommation d'alcool : quantité et chronicité",
            "Facteurs de risque d'hépatite et statut vaccinal",
            "Bilan hépatique, lipasémie, lactates, hémostase si pertinent",
            "Imagerie (échographie, TDM, CPRE/CPRM) disponible",
        ],
        'common_red_flags' => [
            'Hématémèse avec instabilité hémodynamique (HDH massive)',
            'Péritonite (ventre dur, défense, contracture)',
            'Angiocholite (triade de Charcot / pentade de Reynolds)',
            "Insuffisance hépatique aiguë avec encéphalopathie et trouble de l'hémostase",
            "Ischémie mésentérique (douleur disproportionnée + acidose + lactates élevés)",
            'Mégacôlon toxique dans une MICI',
        ],
    ],

    'pulmonology' => [
        'name' => 'Agent de pneumologie',
        'specialty' => 'Pneumologie',
        'description' => "Toux, dyspnée, sifflements, hémoptysies, pneumopathies interstitielles, troubles respiratoires du sommeil.",
        'required_context' => [
            "SpO2 à l'air ambiant ET sous oxygène si utilisé",
            "Fréquence respiratoire, travail respiratoire, capacité à parler en phrases complètes",
            "Paquets-années et expositions professionnelles",
            'EFR (VEMS, CVF, VEMS/CVF, DLCO) si disponibles',
            "Imagerie (RP, TDM thoracique, angio-TDM) résultats",
            "Symptômes du sommeil (ronflements, apnées observées, somnolence diurne) le cas échéant",
        ],
        'common_red_flags' => [
            "Insuffisance respiratoire hypoxémique (SpO2 <90 % à l'air ambiant ou PaO2 <60)",
            "Hémoptysie massive (>200 ml ou compromettant les voies aériennes)",
            "Pneumothorax suffocant (déviation trachéale, instabilité hémodynamique)",
            "EP avec retentissement ventriculaire droit / instabilité hémodynamique (sub-massive / massive)",
            "Asthme aigu sévère résistant aux bronchodilatateurs (silence auscultatoire, épuisement)",
            "Nouveau besoin en oxygène dans une PID (exacerbation aiguë)",
        ],
    ],

    'nephrology' => [
        'name' => 'Agent de néphrologie',
        'specialty' => 'Néphrologie',
        'description' => "IRA, MRC, troubles hydroélectrolytiques et acido-basiques, hypertension, glomérulonéphrites.",
        'required_context' => [
            'Créatinine de base ET valeur actuelle (avec timing)',
            "Statut volémique (orthostatisme, TJV, œdèmes, variation pondérale, diurèse)",
            "Bandelette urinaire et microscopie + ionogramme urinaire / créatininurie",
            "Revue des médicaments (AINS, IEC/ARA II, contraste, aminosides, vancomycine, IPP)",
            "Imagerie récente en cas de suspicion d'obstruction (échographie rénale en première intention)",
            'Indicateurs de sepsis / hypotension / insuffisance cardiaque',
        ],
        'common_red_flags' => [
            'Hyperkaliémie sévère (K >6,5 ou anomalies ECG)',
            "Anurie avec créatinine en hausse (obstruction ou catastrophe vasculaire)",
            'Œdème pulmonaire chez un patient oligurique / dialysé',
            "Sédiment urinaire actif (cylindres hématiques, hématies dysmorphiques) avec créatinine en hausse — GNRP",
            'Hyponatrémie sévère <120 avec signes neurologiques',
            "Encéphalopathie / péricardite urémiques",
        ],
    ],

    'obgyn' => [
        'name' => 'Agent de gynéco-obstétrique',
        'specialty' => 'Obstétrique et gynécologie',
        'description' => "Grossesse, plaintes gynécologiques, contraception, troubles menstruels, oncologie gynécologique.",
        'required_context' => [
            'Date des dernières règles et statut de grossesse (β-hCG qualitative ET quantitative)',
            'Gestité / parité (G_P_)',
            "Profil de saignement, sévérité, présence de caillots / tissus",
            "Caractère et topographie de la douleur",
            "Anamnèse sexuelle, contraceptive et IST le cas échéant",
            'En cas de grossesse : terme, antécédents obstétricaux, statut Rhésus, tendances tensionnelles',
        ],
        'common_red_flags' => [
            'GEU rompue avec instabilité hémodynamique',
            "Prééclampsie sévère / éclampsie / HELLP (TA ≥160/110, protéinurie, atteinte d'organe)",
            'Hémorragie du post-partum (>500 ml voie basse ou >1000 ml césarienne)',
            "Torsion d'annexe (douleur unilatérale sévère brutale ± nausées)",
            'Avortement septique (fièvre + écoulement nauséabond + rétention)',
            "Métrorragies post-ménopausiques (jusqu'à exclusion d'un cancer endométrial)",
        ],
    ],
];
