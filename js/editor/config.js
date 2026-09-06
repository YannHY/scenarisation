// Options pédagogiques, traductions FR/EN et normalisation des libellés.
// Chargé par designer.php ; dépendances injectées par interface.js.
(() => {
"use strict";
window.LearningDesignerModules = { config: (() => {
/* Keep in sync with the --read…--collaborate tokens in css/interface.css and with
   $LEARNING_TYPES in view.php. Duplicated because exports and canvas work
   need literal values, not computed styles. */
const LEARNING_TYPES = [
  { id: "undefined", label: "Non défini", color: "#d1d5db" },
  { id: "read", label: "Lire / Regarder / Écouter", color: "#5bddd3" },
  { id: "investigate", label: "Investiguer", color: "#f19492" },
  { id: "practice", label: "Pratiquer", color: "#c498ec" },
  { id: "produce", label: "Produire", color: "#a2d681" },
  { id: "discuss", label: "Discuter", color: "#85b6f0" },
  { id: "collaborate", label: "Collaborer", color: "#e7b959" }
];

const fontAwesomeIcon = (classes) => `<i class="${classes}" aria-hidden="true"></i>`;

const ICONS = {
  undefined: fontAwesomeIcon("fa-regular fa-circle"),
  read: fontAwesomeIcon("fa-solid fa-book-open"),
  investigate: fontAwesomeIcon("fa-solid fa-magnifying-glass"),
  practice: fontAwesomeIcon("fa-solid fa-dumbbell"),
  produce: fontAwesomeIcon("fa-solid fa-pen-ruler"),
  discuss: fontAwesomeIcon("fa-solid fa-comments"),
  collaborate: fontAwesomeIcon("fa-solid fa-users"),
  whole: fontAwesomeIcon("fa-solid fa-users"),
  subgroups: fontAwesomeIcon("fa-solid fa-user-group"),
  individual: fontAwesomeIcon("fa-solid fa-user"),
  directed: fontAwesomeIcon("fa-solid fa-person-chalkboard"),
  guided: fontAwesomeIcon("fa-solid fa-route"),
  supported: fontAwesomeIcon("fa-solid fa-hand-holding-hand"),
  independent: fontAwesomeIcon("fa-solid fa-person-walking"),
  sync: fontAwesomeIcon("fa-regular fa-clock"),
  async: fontAwesomeIcon("fa-regular fa-calendar-days"),
  onsite: fontAwesomeIcon("fa-solid fa-school"),
  location_based: fontAwesomeIcon("fa-solid fa-location-dot"),
  online: fontAwesomeIcon("fa-solid fa-desktop"),
  hybrid: fontAwesomeIcon("fa-solid fa-shuffle"),
  other: fontAwesomeIcon("fa-solid fa-ellipsis"),
  none: fontAwesomeIcon("fa-regular fa-circle"),
  diagnostic: fontAwesomeIcon("fa-solid fa-magnifying-glass"),
  formative: fontAwesomeIcon("fa-solid fa-pen-to-square"),
  summative: fontAwesomeIcon("fa-solid fa-graduation-cap"),
  certificative: fontAwesomeIcon("fa-solid fa-certificate")
};

const ACTIVITY_TYPE_OPTIONS = LEARNING_TYPES.map((type) => ({
  value: type.id,
  label: type.label,
  short: type.label.split(" ")[0],
  icon: ICONS[type.id]
}));
const GROUP_MODE_OPTIONS = [
  { value: "whole", label: "Groupe entier", short: "Entier", icon: ICONS.whole },
  { value: "subgroups", label: "Sous-groupes", short: "Sous-g.", icon: ICONS.subgroups },
  { value: "individual", label: "Individuel", short: "Indiv.", icon: ICONS.individual }
];
const TEACHING_OPTIONS = [
  {
    value: "directed",
    label: "Enseignement dirigé",
    description: "L’enseignant détermine ce qui se passe à chaque instant, que ce soit en expliquant, en démontrant ou en guidant les élèves pas à pas dans une tâche. Les élèves suivent.",
    short: "Dirigé",
    icon: ICONS.directed
  },
  {
    value: "guided",
    label: "Enseignement guidé",
    description: "Les élèves réalisent la tâche ; l’enseignant fixe la méthode et intervient tout au long pour relancer, corriger et maintenir le cap.",
    short: "Guidé",
    icon: ICONS.guided
  },
  {
    value: "supported",
    label: "Enseignement accompagné",
    description: "Les élèves décident de la manière de procéder et de leur rythme ; l’enseignant répond aux sollicitations ou lorsque le travail s’égare, mais ne le dirige pas.",
    short: "Accompagné",
    icon: ICONS.supported
  },
  {
    value: "independent",
    label: "Enseignement en autonomie",
    description: "Les élèves déterminent leur approche, leur progression et leur rythme dans une tâche conçue par l’enseignant. Aucun accompagnement pendant le travail.",
    short: "Autonomie",
    icon: ICONS.independent
  }
];
const UNDEFINED_TEACHING_OPTION = {
  value: "undefined",
  label: "À définir",
  description: "",
  short: "À définir",
  icon: ICONS.undefined
};
const TEACHING_VALUES = new Set(TEACHING_OPTIONS.map((option) => option.value));
const SYNC_OPTIONS = [
  { value: "sync", label: "Synchrone", short: "Sync", icon: ICONS.sync },
  { value: "async", label: "Asynchrone", short: "Async", icon: ICONS.async }
];
const LOCATION_OPTIONS = [
  { value: "onsite", label: "En classe", short: "Classe", icon: ICONS.onsite },
  { value: "location_based", label: "Sur site", short: "Site", icon: ICONS.location_based },
  { value: "online", label: "En ligne", short: "En ligne", icon: ICONS.online },
  { value: "hybrid", label: "Hybride", short: "Hybride", icon: ICONS.hybrid },
  { value: "other", label: "Autre", short: "Autre", icon: ICONS.other }
];
const LOCATION_VALUES = new Set(LOCATION_OPTIONS.map((option) => option.value));
const SCHOOL_SYSTEM_OPTIONS = [
  { value: "france", labels: { fr: "France", en: "France" } },
  { value: "switzerland", labels: { fr: "Suisse (HarmoS)", en: "Switzerland (HarmoS)" } },
  { value: "united_states", labels: { fr: "États-Unis (K–12)", en: "United States (K–12)" } },
  { value: "belgium_french", labels: { fr: "Belgique — Fédération Wallonie-Bruxelles", en: "Belgium — French Community" } },
  { value: "belgium_flemish", labels: { fr: "Belgique — Communauté flamande", en: "Belgium — Flemish Community" } },
  { value: "belgium_german", labels: { fr: "Belgique — Communauté germanophone", en: "Belgium — German-speaking Community" } },
  { value: "uk_england", labels: { fr: "Royaume-Uni — Angleterre", en: "United Kingdom — England" } },
  { value: "uk_wales", labels: { fr: "Royaume-Uni — Pays de Galles", en: "United Kingdom — Wales" } },
  { value: "uk_scotland", labels: { fr: "Royaume-Uni — Écosse", en: "United Kingdom — Scotland" } },
  { value: "uk_northern_ireland", labels: { fr: "Royaume-Uni — Irlande du Nord", en: "United Kingdom — Northern Ireland" } },
  { value: "european_schools", labels: { fr: "Système des Écoles européennes", en: "European Schools system" } },
  { value: "ib", labels: { fr: "Baccalauréat international (IB)", en: "International Baccalaureate (IB)" } },
  { value: "isced_2011", labels: { fr: "International — ISCED 2011 (CITE)", en: "International — ISCED 2011" } }
];

const SCHOOL_LEVEL_OPTIONS = {
  france: [
    { value: "petite_section", labels: { fr: "Petite section (PS)", en: "Petite section (PS)" }, aliases: ["PS"] },
    { value: "moyenne_section", labels: { fr: "Moyenne section (MS)", en: "Moyenne section (MS)" }, aliases: ["MS", "Moyenne section (MS) / 1re"] },
    { value: "grande_section", labels: { fr: "Grande section (GS)", en: "Grande section (GS)" }, aliases: ["GS", "Grande section (GS) / 2e"] },
    { value: "cp", labels: { fr: "CP", en: "CP" }, aliases: ["CP / 3e"] },
    { value: "ce1", labels: { fr: "CE1", en: "CE1" }, aliases: ["CE1 / 4e"] },
    { value: "ce2", labels: { fr: "CE2", en: "CE2" }, aliases: ["CE2 / 5e"] },
    { value: "cm1", labels: { fr: "CM1", en: "CM1" }, aliases: ["CM1 / 6e"] },
    { value: "cm2", labels: { fr: "CM2", en: "CM2" }, aliases: ["CM2 / 7e"] },
    { value: "sixieme", labels: { fr: "6e", en: "6e" }, aliases: ["6e / 8e"] },
    { value: "cinquieme", labels: { fr: "5e", en: "5e" }, aliases: ["5e / 9e"] },
    { value: "quatrieme", labels: { fr: "4e", en: "4e" }, aliases: ["4e / 10e"] },
    { value: "troisieme", labels: { fr: "3e", en: "3e" }, aliases: ["3e / 11e"] },
    { value: "seconde", labels: { fr: "Seconde", en: "Seconde" }, aliases: ["Seconde / Secondaire II – 1re année"] },
    { value: "premiere", labels: { fr: "Première", en: "Première" }, aliases: ["Première / Secondaire II – 2e année"] },
    { value: "terminale", labels: { fr: "Terminale", en: "Terminale" }, aliases: ["Terminale / Secondaire II – 3e année"] }
  ],
  switzerland: [
    { value: "ch_1p", labels: { fr: "1P — 1re année primaire", en: "1P — Primary year 1" }, aliases: ["1P", "1re"] },
    { value: "ch_2p", labels: { fr: "2P — 2e année primaire", en: "2P — Primary year 2" }, aliases: ["2P", "2e"] },
    { value: "ch_3p", labels: { fr: "3P — 3e année primaire", en: "3P — Primary year 3" }, aliases: ["3P"] },
    { value: "ch_4p", labels: { fr: "4P — 4e année primaire", en: "4P — Primary year 4" }, aliases: ["4P"] },
    { value: "ch_5p", labels: { fr: "5P — 5e année primaire", en: "5P — Primary year 5" }, aliases: ["5P"] },
    { value: "ch_6p", labels: { fr: "6P — 6e année primaire", en: "6P — Primary year 6" }, aliases: ["6P"] },
    { value: "ch_7p", labels: { fr: "7P — 7e année primaire", en: "7P — Primary year 7" }, aliases: ["7P"] },
    { value: "ch_8p", labels: { fr: "8P — 8e année primaire", en: "8P — Primary year 8" }, aliases: ["8P", "8e"] },
    { value: "ch_9s", labels: { fr: "9e — secondaire I", en: "Year 9 — lower secondary" }, aliases: ["9S", "9e"] },
    { value: "ch_10s", labels: { fr: "10e — secondaire I", en: "Year 10 — lower secondary" }, aliases: ["10S", "10e"] },
    { value: "ch_11s", labels: { fr: "11e — secondaire I", en: "Year 11 — lower secondary" }, aliases: ["11S", "11e"] },
    { value: "ch_sec2_1", labels: { fr: "Secondaire II — 1re année", en: "Upper secondary — year 1" }, aliases: ["Secondaire II – 1re année"] },
    { value: "ch_sec2_2", labels: { fr: "Secondaire II — 2e année", en: "Upper secondary — year 2" }, aliases: ["Secondaire II – 2e année"] },
    { value: "ch_sec2_3", labels: { fr: "Secondaire II — 3e année", en: "Upper secondary — year 3" }, aliases: ["Secondaire II – 3e année"] }
  ],
  united_states: [
    { value: "us_k", labels: { fr: "Kindergarten (K)", en: "Kindergarten (K)" }, aliases: ["K", "Kindergarten"] },
    ...Array.from({ length: 12 }, (_, index) => {
      const grade = index + 1;
      return {
        value: `us_grade_${grade}`,
        labels: { fr: `Grade ${grade}`, en: `Grade ${grade}` },
        aliases: [`${grade}${grade === 1 ? "st" : grade === 2 ? "nd" : grade === 3 ? "rd" : "th"} grade`]
      };
    })
  ],
  belgium_french: [
    ...Array.from({ length: 3 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_fr_m${year}`,
        labels: { fr: `M${year} — ${year}${year === 1 ? "re" : "e"} maternelle`, en: `M${year} — nursery year ${year}` },
        aliases: [`M${year}`, `${year}${year === 1 ? "re" : "e"} maternelle`]
      };
    }),
    ...Array.from({ length: 6 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_fr_p${year}`,
        labels: { fr: `P${year} — ${year}${year === 1 ? "re" : "e"} primaire`, en: `P${year} — primary year ${year}` },
        aliases: [`P${year}`, `${year}${year === 1 ? "re" : "e"} primaire`]
      };
    }),
    ...Array.from({ length: 7 }, (_, index) => {
      const year = index + 1;
      const optional = year === 7 ? " — selon la filière" : "";
      return {
        value: `be_fr_s${year}`,
        labels: { fr: `S${year} — ${year}${year === 1 ? "re" : "e"} secondaire${optional}`, en: `S${year} — secondary year ${year}${year === 7 ? " — depending on pathway" : ""}` },
        aliases: [`S${year}`, `${year}${year === 1 ? "re" : "e"} secondaire`]
      };
    })
  ],
  belgium_flemish: [
    ...Array.from({ length: 3 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_nl_k${year}`,
        labels: { fr: `K${year} — ${year}e kleuterklas`, en: `K${year} — kindergarten year ${year}` },
        aliases: [`K${year}`, `${year}e kleuterklas`]
      };
    }),
    ...Array.from({ length: 6 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_nl_l${year}`,
        labels: { fr: `L${year} — ${year}e leerjaar lager onderwijs`, en: `L${year} — primary year ${year}` },
        aliases: [`L${year}`, `${year}e leerjaar lager onderwijs`]
      };
    }),
    ...Array.from({ length: 7 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_nl_s${year}`,
        labels: { fr: `S${year} — ${year}e leerjaar secundair onderwijs`, en: `S${year} — secondary year ${year}` },
        aliases: [`S${year}`, `${year}e leerjaar secundair onderwijs`]
      };
    })
  ],
  belgium_german: [
    ...Array.from({ length: 3 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_de_k${year}`,
        labels: { fr: `K${year} — ${year}. Kindergartenjahr`, en: `K${year} — kindergarten year ${year}` },
        aliases: [`K${year}`, `${year}. Kindergartenjahr`]
      };
    }),
    ...Array.from({ length: 6 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_de_p${year}`,
        labels: { fr: `P${year} — ${year}. Primarschuljahr`, en: `P${year} — primary year ${year}` },
        aliases: [`P${year}`, `${year}. Primarschuljahr`]
      };
    }),
    ...Array.from({ length: 7 }, (_, index) => {
      const year = index + 1;
      return {
        value: `be_de_s${year}`,
        labels: { fr: `S${year} — ${year}. Sekundarschuljahr${year === 7 ? " (professionnel)" : ""}`, en: `S${year} — secondary year ${year}${year === 7 ? " (vocational)" : ""}` },
        aliases: [`S${year}`, `${year}. Sekundarschuljahr`]
      };
    })
  ],
  uk_england: [
    { value: "uk_england_nursery", labels: { fr: "Nursery", en: "Nursery" } },
    { value: "uk_england_reception", labels: { fr: "Reception", en: "Reception" } },
    ...Array.from({ length: 13 }, (_, index) => ({
      value: `uk_england_year_${index + 1}`,
      labels: { fr: `Year ${index + 1}`, en: `Year ${index + 1}` }
    }))
  ],
  uk_wales: [
    { value: "uk_wales_nursery", labels: { fr: "Nursery", en: "Nursery" } },
    { value: "uk_wales_reception", labels: { fr: "Reception", en: "Reception" } },
    ...Array.from({ length: 13 }, (_, index) => ({
      value: `uk_wales_year_${index + 1}`,
      labels: { fr: `Year ${index + 1}`, en: `Year ${index + 1}` }
    }))
  ],
  uk_scotland: [
    { value: "uk_scotland_early_learning", labels: { fr: "Petite enfance (Nursery)", en: "Early learning and childcare (Nursery)" }, aliases: ["Nursery"] },
    ...Array.from({ length: 7 }, (_, index) => ({
      value: `uk_scotland_p${index + 1}`,
      labels: { fr: `P${index + 1} — primaire`, en: `P${index + 1} — primary` }
    })),
    ...Array.from({ length: 6 }, (_, index) => ({
      value: `uk_scotland_s${index + 1}`,
      labels: { fr: `S${index + 1} — secondaire`, en: `S${index + 1} — secondary` }
    }))
  ],
  uk_northern_ireland: [
    { value: "uk_northern_ireland_preschool", labels: { fr: "Préscolaire (Pre-school)", en: "Pre-school" } },
    ...Array.from({ length: 7 }, (_, index) => ({
      value: `uk_northern_ireland_p${index + 1}`,
      labels: { fr: `P${index + 1} — primaire`, en: `P${index + 1} — primary` }
    })),
    ...Array.from({ length: 7 }, (_, index) => ({
      value: `uk_northern_ireland_year_${index + 8}`,
      labels: { fr: `Year ${index + 8}`, en: `Year ${index + 8}` }
    }))
  ],
  european_schools: [
    ...Array.from({ length: 2 }, (_, index) => ({
      value: `eu_school_n${index + 1}`,
      labels: { fr: `N${index + 1} — cycle maternel`, en: `N${index + 1} — nursery cycle` },
      aliases: [`N${index + 1}`]
    })),
    ...Array.from({ length: 5 }, (_, index) => ({
      value: `eu_school_p${index + 1}`,
      labels: { fr: `P${index + 1} — cycle primaire`, en: `P${index + 1} — primary cycle` },
      aliases: [`P${index + 1}`]
    })),
    ...Array.from({ length: 7 }, (_, index) => {
      const year = index + 1;
      const cycleFr = year <= 3
        ? "cycle d’observation"
        : year <= 5
          ? "cycle de pré-orientation"
          : "cycle du Baccalauréat européen";
      const cycleEn = year <= 3
        ? "observation cycle"
        : year <= 5
          ? "pre-orientation cycle"
          : "European Baccalaureate cycle";
      return {
        value: `eu_school_s${year}`,
        labels: { fr: `S${year} — ${cycleFr}`, en: `S${year} — ${cycleEn}` },
        aliases: [`S${year}`]
      };
    })
  ],
  ib: [
    { value: "ib_pyp", labels: { fr: "PP — Programme primaire (3–12 ans)", en: "PYP — Primary Years Programme (ages 3–12)" }, aliases: ["PP", "PYP", "Programme primaire", "Primary Years Programme"] },
    { value: "ib_myp_1", labels: { fr: "PEI — Programme d’éducation intermédiaire — 1re année", en: "MYP — Middle Years Programme — year 1" }, aliases: ["PEI 1", "MYP 1", "MYP1"] },
    { value: "ib_myp_2", labels: { fr: "PEI — Programme d’éducation intermédiaire — 2e année", en: "MYP — Middle Years Programme — year 2" }, aliases: ["PEI 2", "MYP 2", "MYP2"] },
    { value: "ib_myp_3", labels: { fr: "PEI — Programme d’éducation intermédiaire — 3e année", en: "MYP — Middle Years Programme — year 3" }, aliases: ["PEI 3", "MYP 3", "MYP3"] },
    { value: "ib_myp_4", labels: { fr: "PEI — Programme d’éducation intermédiaire — 4e année", en: "MYP — Middle Years Programme — year 4" }, aliases: ["PEI 4", "MYP 4", "MYP4"] },
    { value: "ib_myp_5", labels: { fr: "PEI — Programme d’éducation intermédiaire — 5e année", en: "MYP — Middle Years Programme — year 5" }, aliases: ["PEI 5", "MYP 5", "MYP5"] },
    { value: "ib_dp_1", labels: { fr: "Diplôme — Programme du diplôme — 1re année", en: "DP — Diploma Programme — year 1" }, aliases: ["Diplôme 1", "DP 1", "DP1"] },
    { value: "ib_dp_2", labels: { fr: "Diplôme — Programme du diplôme — 2e année", en: "DP — Diploma Programme — year 2" }, aliases: ["Diplôme 2", "DP 2", "DP2"] },
    { value: "ib_cp_1", labels: { fr: "POP — Programme à orientation professionnelle — 1re année", en: "CP — Career-related Programme — year 1" }, aliases: ["POP 1", "CP 1", "CP1"] },
    { value: "ib_cp_2", labels: { fr: "POP — Programme à orientation professionnelle — 2e année", en: "CP — Career-related Programme — year 2" }, aliases: ["POP 2", "CP 2", "CP2"] },
  ],
  isced_2011: [
    { value: "isced_0", labels: { fr: "ISCED 0 — Éducation de la petite enfance", en: "ISCED 0 — Early childhood education" }, aliases: ["CITE 0"] },
    { value: "isced_1", labels: { fr: "ISCED 1 — Enseignement primaire", en: "ISCED 1 — Primary education" }, aliases: ["CITE 1"] },
    { value: "isced_2", labels: { fr: "ISCED 2 — Premier cycle du secondaire", en: "ISCED 2 — Lower secondary education" }, aliases: ["CITE 2"] },
    { value: "isced_3", labels: { fr: "ISCED 3 — Deuxième cycle du secondaire", en: "ISCED 3 — Upper secondary education" }, aliases: ["CITE 3"] },
    { value: "isced_4", labels: { fr: "ISCED 4 — Post-secondaire non supérieur", en: "ISCED 4 — Post-secondary non-tertiary education" }, aliases: ["CITE 4"] },
    { value: "isced_5", labels: { fr: "ISCED 5 — Enseignement supérieur de cycle court", en: "ISCED 5 — Short-cycle tertiary education" }, aliases: ["CITE 5"] },
    { value: "isced_6", labels: { fr: "ISCED 6 — Licence ou équivalent", en: "ISCED 6 — Bachelor’s or equivalent level" }, aliases: ["CITE 6"] },
    { value: "isced_7", labels: { fr: "ISCED 7 — Master ou équivalent", en: "ISCED 7 — Master’s or equivalent level" }, aliases: ["CITE 7"] },
    { value: "isced_8", labels: { fr: "ISCED 8 — Doctorat ou équivalent", en: "ISCED 8 — Doctoral or equivalent level" }, aliases: ["CITE 8"] }
  ]
};
const PARTITION_TYPE_OPTIONS = [
  { type: "locationMode",    labelKey: "partitionTypeLocation", options: LOCATION_OPTIONS },
  { type: "groupMode",       labelKey: "partitionTypeGroup",    options: GROUP_MODE_OPTIONS },
  { type: "syncMode",        labelKey: "partitionTypeSync",     options: SYNC_OPTIONS },
  { type: "teachingMode", labelKey: "partitionTypeTeaching", options: TEACHING_OPTIONS },
];
const EVAL_OPTIONS = [
  { value: "none", label: "Aucune", short: "Aucune", icon: ICONS.none },
  { value: "diagnostic", label: "Diagnostique", short: "Diag.", icon: ICONS.diagnostic },
  { value: "formative", label: "Formative", short: "Form.", icon: ICONS.formative },
  { value: "summative", label: "Sommative", short: "Somm.", icon: ICONS.summative },
  { value: "certificative", label: "Certificative", short: "Certif.", icon: ICONS.certificative }
];

const AIAS_VERSION = "2.1";
const AIAS_LEVELS = [
  { level: 1, labelKey: "aiasLevel1Label", descriptionKey: "aiasLevel1Description" },
  { level: 2, labelKey: "aiasLevel2Label", descriptionKey: "aiasLevel2Description" },
  { level: 3, labelKey: "aiasLevel3Label", descriptionKey: "aiasLevel3Description" },
  { level: 4, labelKey: "aiasLevel4Label", descriptionKey: "aiasLevel4Description" },
  { level: 5, labelKey: "aiasLevel5Label", descriptionKey: "aiasLevel5Description" }
];
const AIAS_TRIGGER_ICON = "fa-wand-magic-sparkles";


const DEFAULT_DAY_HOURS = 7;

const BLOOM_TAXONOMY = {
  fr: [
    {
      id: "souvenir", label: "Se souvenir",
      verbs: ["Citer", "Définir", "Décrire", "Dupliquer", "Énumérer", "Identifier", "Lister", "Localiser", "Mémoriser", "Nommer", "Rappeler", "Reconnaître", "Reproduire", "Retrouver"]
    },
    {
      id: "comprendre", label: "Comprendre",
      verbs: ["Clarifier", "Classer", "Comparer", "Décrire", "Distinguer", "Exemplifier", "Expliquer", "Généraliser", "Illustrer", "Inférer", "Interpréter", "Paraphraser", "Reformuler", "Résumer", "Traduire"]
    },
    {
      id: "appliquer", label: "Appliquer",
      verbs: ["Appliquer", "Calculer", "Choisir", "Compléter", "Construire", "Démontrer", "Employer", "Exécuter", "Mettre en œuvre", "Modifier", "Pratiquer", "Produire", "Résoudre", "Utiliser"]
    },
    {
      id: "analyser", label: "Analyser",
      verbs: ["Analyser", "Attribuer", "Comparer", "Contraster", "Décomposer", "Déconstruire", "Différencier", "Discriminer", "Distinguer", "Examiner", "Expérimenter", "Inférer", "Organiser", "Questionner", "Structurer"]
    },
    {
      id: "evaluer", label: "Évaluer",
      verbs: ["Apprécier", "Argumenter", "Choisir", "Comparer", "Conclure", "Critiquer", "Décider", "Défendre", "Estimer", "Évaluer", "Juger", "Justifier", "Recommander", "Sélectionner"]
    },
    {
      id: "creer", label: "Créer",
      verbs: ["Assembler", "Combiner", "Composer", "Concevoir", "Construire", "Créer", "Développer", "Élaborer", "Formuler", "Générer", "Imaginer", "Inventer", "Organiser", "Planifier", "Produire"]
    }
  ],
  en: [
    {
      id: "remember", label: "Remember",
      verbs: ["Cite", "Define", "Describe", "Duplicate", "Enumerate", "Find out", "Identify", "Label", "List", "Locate", "Memorize", "Name", "Recall", "Recognize", "Reproduce", "Retrieve"]
    },
    {
      id: "understand", label: "Understand",
      verbs: ["Clarify", "Classify", "Compare", "Describe", "Distinguish", "Exemplify", "Explain", "Generalize", "Identify", "Illustrate", "Infer", "Interpret", "Paraphrase", "Summarize", "Translate"]
    },
    {
      id: "apply", label: "Apply",
      verbs: ["Apply", "Calculate", "Choose", "Complete", "Construct", "Demonstrate", "Execute", "Implement", "Modify", "Practice", "Produce", "Resolve", "Use"]
    },
    {
      id: "analyze", label: "Analyze",
      verbs: ["Analyze", "Attribute", "Compare", "Contrast", "Deconstruct", "Differentiate", "Discriminate", "Distinguish", "Examine", "Experiment", "Infer", "Organize", "Question", "Structure"]
    },
    {
      id: "evaluate", label: "Evaluate",
      verbs: ["Appreciate", "Argue", "Choose", "Compare", "Conclude", "Criticize", "Decide", "Defend", "Estimate", "Evaluate", "Judge", "Justify", "Recommend", "Select"]
    },
    {
      id: "create", label: "Create",
      verbs: ["Assemble", "Combine", "Compose", "Conceive", "Construct", "Create", "Design", "Develop", "Elaborate", "Formulate", "Generate", "Imagine", "Invent", "Organize", "Plan", "Produce"]
    }
  ]
};


const I18N = {
  fr: {
    docTitle: "Interface de conception d’apprentissage - Prototype",
    skipLink: "Aller au contenu principal",
    appTitle: "Interface de conception d’apprentissage",
    tabSettings: "Paramètres",
    tabAnalysis: "Analyse",
    collapsePanel: "Replier le panneau",
    expandPanel: "Déplier le panneau",
    addMoment: "Ajouter un moment",
    addMomentCompact: "Ajouter",
    createMoment: "Créer un moment",
    momentAdded: "Moment créé.",
    expandNotes: "Déplier les notes",
    collapseNotes: "Replier les notes",
    hideIntentions: "Masquer les intentions",
    showIntentions: "Afficher les intentions",
    new: "Nouveau",
    import: "Importer",
    export: "Exporter",
    save: "Enregistrer",
    saveCopy: "Enregistrer une copie",
    share: "Partager",
    saved: "Enregistré",
    savedLocal: "Modifications mises à jour.",
    info: "Information",
    toolbarRegion: "Actions du design",
    analysisTitle: "Expérience d’apprentissage",
    analysisTooltip: "Synthèse visuelle de la répartition du temps conçu selon les choix pédagogiques",
    analysisAiasTitle: "AIAS · Répartition pondérée par la durée",
    analysisAiasTooltip: "Répartition du temps conçu selon le niveau d'usage de l'IA autorisé pour les activités",
    metaNameLabel: "Titre",
    metaNameTooltip: "Nom donné au scénario pédagogique",
    metaLearningLabel: "Temps d'apprentissage",
    metaLearningTooltip: "Temps d'apprentissage effectivement utilisé",
    metaDesignedLabel: "Temps conçu",
    metaDesignedTooltip: "Durée prévue par l'enseignant lors de l'élaboration de son scénario",
    metaDayLabel: "1 jour =",
    metaDayTooltip: "Nombre d'heures d'apprentissage comptabilisées pour une journée",
    metaDescriptionLabel: "Description",
    metaDescriptionTooltip: "Présentation générale du scénario pédagogique et de son contexte",
    metaCommandLabel: "Commande institutionnelle",
    metaCommandTooltip: "Demande, cadre et contraintes formulés par l'institution à l'origine du scénario",
    metaDeliveryLabel: "Mode",
    metaDeliveryTooltip: "Modalité générale de la formation : présentiel, distanciel ou hybride",
    metaSchoolSystemLabel: "Système / classification",
    metaSchoolSystemTooltip: "Système éducatif ou classification internationale servant à déterminer le niveau",
    metaLevelLabel: "Niveau",
    metaLevelTooltip: "Niveau d'enseignement visé par le scénario pédagogique",
    metaLevelChooseSystemFirst: "Choisissez d’abord un système ou une classification",
    metaLevelPlaceholder: "Choisissez un niveau",
    schoolSystemFrance: "France",
    schoolSystemSwitzerland: "Suisse (HarmoS)",
    schoolSystemUnitedStates: "États-Unis (K–12)",
    schoolSystemBelgiumFrench: "Belgique — Fédération Wallonie-Bruxelles",
    schoolSystemBelgiumFlemish: "Belgique — Communauté flamande",
    schoolSystemBelgiumGerman: "Belgique — Communauté germanophone",
    schoolSystemUnitedKingdomEngland: "Royaume-Uni — Angleterre",
    schoolSystemUnitedKingdomWales: "Royaume-Uni — Pays de Galles",
    schoolSystemUnitedKingdomScotland: "Royaume-Uni — Écosse",
    schoolSystemUnitedKingdomNorthernIreland: "Royaume-Uni — Irlande du Nord",
    schoolSystemEuropeanSchools: "Système des Écoles européennes",
    schoolSystemIB: "Baccalauréat international (IB)",
    schoolSystemIsced: "International — ISCED 2011 (CITE)",
    schoolSystemsNationalGroup: "Systèmes nationaux",
    schoolSystemsTransnationalGroup: "Systèmes transnationaux",
    schoolSystemsInternationalGroup: "Classification internationale",
    metaSizeLabel: "Taille du groupe",
    metaSizeTooltip: "Nombre d'apprenants concernés par le scénario pédagogique",
    metaDesignersLabel: "Concepteur(s)",
    metaDesignersTooltip: "Personnes ayant élaboré le scénario pédagogique",
    metaTrainersLabel: "Enseignant(s)",
    metaTrainersTooltip: "Personnes chargées de mettre en œuvre le scénario pédagogique",
    metaPersonasLabel: "Objectifs",
    metaPersonasTooltip: "Finalités et intentions pédagogiques poursuivies par la formation",
    metaSlidersLabel: "Résultats",
    outcomesLabel: "Acquis d'apprentissage\u00a0*",
    outcomesTooltip: "Ce que les apprenants devront savoir, comprendre ou savoir faire à l'issue de la formation. Cette partie est optionnelle.",
    addOutcome: "+ Acquis",
    outcomeTextPlaceholder: "Décrivez cet acquis...",
    deleteOutcome: "Supprimer l'acquis",
    changeVerb: "Modifier le verbe",
    bloomTitle: "Taxonomie de Bloom",
    bloomSubtitle: "Sélectionnez une catégorie ou un verbe d'action",
    bloomAdd: "Ajouter",
    bloomEdit: "Modifier",
    unitDays: "jours",
    unitHours: "heures",
    unitMinutes: "minutes",
    modeOnsite: "Présentiel",
    modeOnline: "Distanciel",
    modeHybrid: "Hybride",
    activityModeClassroom: "En classe",
    activityModeLocation: "Sur site",
    activityModeOnline: "En ligne",
    activityModeBlended: "Hybride",
    activityModeOther: "Autre",
    importTitle: "Importer un scénario",
    importModalDesc: "Partez d’un modèle prérempli, ou chargez un fichier exporté depuis cette application. Le design actuel sera remplacé.",
    importFromFileTitle: "Depuis mon ordinateur",
    importChooseFile: "Choisir un fichier…",
    importDropTitle: "Glissez-déposez un fichier ici",
    importDropActive: "Relâchez pour importer",
    importDropHint: "ou choisissez-le sur votre ordinateur",
    importFileFormats: "LDJ, JSON, CSV, Excel, Markdown",
    importModelsTitle: "Bibliothèque de modèles",
    importModelsLink: "Voir la bibliothèque",
    importModelsSearchLabel: "Rechercher un modèle",
    importModelsSearchPlaceholder: "Rechercher un modèle…",
    importModelsFamilyLabel: "Famille de modèles",
    importModelsFamilyAll: "Toutes les familles",
    importModelsLoading: "Chargement des modèles…",
    importModelsCount: "{count} modèles disponibles.",
    importModelsCountOne: "1 modèle disponible.",
    importModelsNone: "Aucun modèle ne correspond à cette recherche.",
    importModelsError: "Bibliothèque de modèles indisponible. L’import de fichier reste possible.",
    importModelsUnitMoments: "moments",
    importModelsUnitActivities: "activités",
    importModelsToComplete: "À compléter :",
    importModelPreviewButton: "Visualiser",
    importModelUseButton: "Importer",
    importModelPreviewEyebrow: "Aperçu du scénario",
    importModelPreviewBack: "Retour aux modèles",
    importModelPreviewLoading: "Chargement de l’aperçu…",
    importModelPreviewError: "Impossible d’afficher l’aperçu de ce modèle.",
    importModelPreviewObjectives: "Objectifs du moment",
    importModelPreviewTeacher: "Déroulement",
    importModelPreviewStudents: "Consigne aux élèves",
    importModelPreviewActivity: "Activité {number}",
    importModelApplied: "Modèle chargé : {name}",
    importModelFailed: "Ce modèle n’a pas pu être chargé.",
    exportTitle: "Exporter le design",
    exportScopeTitle: "Contenu à exporter",
    exportScopeFull: "Export enseignant",
    exportScopeFullDescription: "Toutes les informations du scénario pédagogique.",
    exportScopeStudents: "Export élève",
    exportScopeStudentsDescription: "Les séances, les activités et les consignes adressées aux élèves.",
    exportMomentsTitle: "Moments à exporter",
    exportMomentsAll: "Tous les moments",
    exportMomentsEmpty: "Aucun moment à exporter.",
    exportMomentsSelection: (selected, total) => `${selected} sur ${total} sélectionné${selected === 1 ? "" : "s"}`,
    format: "Format",
    exportFilename: "Nom du fichier",
    exportPreviewCopy: "Le contenu exporté est lisible ci-dessous. Vous pouvez le copier ou télécharger le fichier.",
    exportPreviewTitle: "Prévisualisation",
    exportDownloadOnly: "Ce format est destiné au téléchargement. Le contenu brut n'est pas affiché ni copiable.",
    copy: "Copier",
    download: "Télécharger",
    cancel: "Annuler",
    validate: "Valider",
    close: "Fermer",
    boardRegion: "Séquences de séances",
    sessionNotes: "Notes",
    addLearningType: "+ Activité",
    sessionPrefix: "Séance",
    viewModeLabel: "Mode d'affichage des séances",
    viewList: "Liste",
    viewColumns: "Colonnes",
    sessionMoveHintColumns: "Alt+Flèche gauche/droite pour déplacer.",
    sessionMoveHintList: "Alt+Flèche haut/bas pour déplacer.",
    activityMoveHint: "Alt+Flèche haut/bas pour déplacer dans la séance.",
    sessionTitleLabel: "Titre de la séance",
    sessionObjectivesLabel: "Objectifs du moment",
    sessionActivitiesLabel: "Activités de la séance",
    activityLabel: "Activité",
    activityDurationLabel: "Durée en minutes de l'activité",
    activityDescriptionLabel: "Description de l'activité",
    activityInstructionsLabel: "Consignes pour les élèves",
    activityNotesLabel: "Notes de l'activité",
    sessionNotesLabel: "Notes de la séance",
    duplicateMoment: "Dupliquer le moment",
    duplicateActivity: "Dupliquer l’activité",
    deleteSession: "Supprimer la séance",
    deleteActivity: "Supprimer l'activité",
    momentDuplicated: "Moment dupliqué juste après l’original.",
    activityDuplicated: "Activité dupliquée juste après l’originale.",
    copySuffix: "copie",
    activityDeleted: "Activité supprimée.",
    activityAdded: "Activité ajoutée.",
    sessionDeleted: "Séance supprimée.",
    selectTools: "Sélectionner des compétences",
    toolPickerTitle: "Sélectionner des compétences",
    toolPickerFrameworkLabel: "Cadre de compétences",
    toolPickerSource: "Consulter la source",
    toolPickerClose: "Fermer",
    toolsAriaLabel: "Compétences sélectionnées",
    removeToolAriaLabel: (name) => `Retirer ${name}`,
    toolCount: (n) => n === 1 ? "1 compétence" : `${n} compétences`,
    groupTitleType: "Type d'apprentissage",
    analysisLearningTooltip: "Répartition du temps conçu entre les six types d'apprentissage",
    groupTitleGroup: "Groupe",
    analysisGroupTooltip: "Répartition du temps conçu entre travail individuel, en sous-groupes et en groupe entier",
    groupTitleTeaching: "Enseignement",
    analysisTeachingTooltip: "Répartition du temps conçu entre enseignement dirigé, guidé, accompagné et autonome",
    groupTitlePacing: "Rythme",
    analysisPacingTooltip: "Répartition du temps conçu entre activités synchrones et asynchrones",
    groupTitleMode: "Mode de formation",
    analysisDeliveryTooltip: "Répartition du temps conçu selon le lieu et la modalité de réalisation des activités",
    groupTitleEvaluation: "Évaluation",
    analysisEvaluationTooltip: "Répartition du temps conçu selon le type d'évaluation prévu",
    aiasFieldLabel: "Place de l’IA dans la tâche · AIAS 2.1",
    aiasUndecided: "À définir",
    aiasNotApplicable: "Non pertinent",
    aiasPanelIntro: "Choisissez le rôle de l’IA dans cette tâche. Les niveaux décrivent des conceptions différentes, sans hiérarchie entre elles.",
    aiasLevelsAriaLabel: "Niveau AIAS de l’activité",
    aiasAttributionPrefix: "Basé sur",
    aiasLevelPrefix: "Niveau",
    aiasUpdated: "Place de l’IA mise à jour.",
    aiasLevel1Label: "Sans IA",
    aiasLevel1Description: "Tâche réalisée sans IA, dans un environnement contrôlé, pour évaluer les acquis propres de l’élève.",
    aiasLevel2Label: "Planification avec l’IA",
    aiasLevel2Description: "L’IA peut soutenir l’exploration, la recherche et la planification ; la réalisation reste autonome.",
    aiasLevel3Label: "Collaboration avec l’IA",
    aiasLevel3Description: "L’IA contribue au travail ; l’élève évalue, modifie et intègre ses productions.",
    aiasLevel4Label: "IA pleinement intégrée",
    aiasLevel4Description: "L’IA est pleinement intégrée ; l’élève la dirige avec esprit critique et expertise disciplinaire.",
    aiasLevel5Label: "Exploration de l’IA",
    aiasLevel5Description: "L’élève explore et co-conçoit des usages créatifs de l’IA pour produire des idées ou des solutions nouvelles.",
    newActivityDescription: "Nouvelle activité",
    sessionTitlePlaceholder: "Titre du moment",
    activityDescriptionPlaceholder: "Décrivez l'activité...",
    activityInstructionsPlaceholder: "Indiquez les consignes données aux élèves...",
    newDesignConfirm: "Créer un nouveau design et écraser le contenu actuel ?",
    newDesignModalTitle: "Nouveau design",
    newDesignModalMsg: "Vous allez créer un nouveau design vierge. Si vous n'avez pas enregistré le design actuel, il sera perdu.",
    newDesignModalConfirm: "Créer un nouveau design",
    importInvalid: "Fichier invalide. Importez un LDJ, JSON, CSV, Excel ou Markdown exporté depuis cette application.",
    commandPlaceholder: "Collez ici la commande institutionnelle déjà définie...",
    personasPlaceholder: "Décrivez les objectifs de la formation...",
    slidersPlaceholder: "Décrivez les résultats attendus...",
    sessionNotesPlaceholder: "Notes de la séance...",
    sessionObjectivesPlaceholder: "Objectifs du moment...",
    sessionIntentionsLabel: "Choix pédagogiques",
    sessionIntentionsPlaceholder: "Choix pédagogiques (ex. : pourquoi cet ordre d'activités ? quelle alternance de modalités ? quel rythme ?)",
    activityNotesPlaceholder: "Notes de l'activité...",
    durationMinutesSr: "Durée en minutes",
    fullscreen: "Plein écran",
    closeFullscreen: "Quitter le plein écran",
    markdownToolbarLabel: "Barre de formatage Markdown",
    mdBold: "Gras",
    mdItalic: "Italique",
    mdHeading: "Titre",
    mdList: "Liste à puces",
    mdOrderedList: "Liste numérotée",
    mdQuote: "Citation",
    mdLink: "Lien",
    mdPlaceholderBold: "texte en gras",
    mdPlaceholderItalic: "texte en italique",
    mdPlaceholderHeading: "Titre",
    mdPlaceholderList: "élément de liste",
    mdPlaceholderOrderedList: "élément de liste",
    mdPlaceholderQuote: "citation",
    mdPlaceholderLinkText: "texte du lien",
    mdPlaceholderLinkUrl: "https://",
    uiLanguage: "Langue de l’interface",
    moved: "Élément déplacé.",
    an01: "Un ou plusieurs graphiques peuvent être incorrects, car une ou plusieurs activités n’ont pas de durée valide.",
    an02: "Les graphiques ne peuvent pas être calculés, car aucune durée d’activité n’est définie.",
    an03: "Le graphe social peut être incorrect, car un ou plusieurs types d’apprentissage n’ont pas de taille de groupe définie.",
    an04: "Le graphique « Enseignement » peut être incorrect, car une ou plusieurs activités n’ont pas ce paramètre défini.",
    an05: "Le graphique « Rythme » peut être incorrect, car une ou plusieurs activités n’ont pas ce paramètre défini.",
    an06: "Le graphique « Mode de formation » peut être incorrect, car une ou plusieurs activités n’ont pas ce paramètre défini.",
    an07: "Le temps conçu dépasse le temps d’apprentissage déclaré.",
    an08: "Le temps d’apprentissage n’est pas défini, mais des activités ont une durée.",
    an09: "Aucun type d’apprentissage défini : précisez le type de chaque activité pour obtenir une analyse pertinente.",
    lt_undefined: "Non défini",
    lt_read: "Lire / Regarder / Écouter",
    lt_investigate: "Investiguer",
    lt_practice: "Pratiquer",
    lt_produce: "Produire",
    lt_discuss: "Discuter",
    lt_collaborate: "Collaborer",
    group_whole: "Groupe entier",
    group_subgroups: "Sous-groupes",
    group_individual: "Individuel",
    teaching_undefined: "À définir",
    teaching_directed: "Enseignement dirigé",
    teaching_directed_description: "L’enseignant détermine ce qui se passe à chaque instant, que ce soit en expliquant, en démontrant ou en guidant les élèves pas à pas dans une tâche. Les élèves suivent.",
    teaching_guided: "Enseignement guidé",
    teaching_guided_description: "Les élèves réalisent la tâche ; l’enseignant fixe la méthode et intervient tout au long pour relancer, corriger et maintenir le cap.",
    teaching_supported: "Enseignement accompagné",
    teaching_supported_description: "Les élèves décident de la manière de procéder et de leur rythme ; l’enseignant répond aux sollicitations ou lorsque le travail s’égare, mais ne le dirige pas.",
    teaching_independent: "Enseignement en autonomie",
    teaching_independent_description: "Les élèves déterminent leur approche, leur progression et leur rythme dans une tâche conçue par l’enseignant. Aucun accompagnement pendant le travail.",
    sync_sync: "Synchrone",
    sync_async: "Asynchrone",
    eval_none: "Aucune évaluation",
    eval_diagnostic: "Diagnostique",
    eval_formative: "Formative",
    eval_summative: "Sommative",
    eval_certificative: "Certificative",
    infoTitle: "À propos",
    footerHelp: "Aide",
    footerSharedDesigns: "Scénarios partagés",
    infoP1: "Cette application web monopage s’inspire de l’UCL Learning Designer :",
    infoP2: "(UCL Knowledge Lab, UCL Institute of Education, 2013-2026).",
    infoP3: "Traitement local par défaut : les données restent dans votre navigateur, sauf si vous vous connectez et enregistrez explicitement une production sur votre compte.",
    infoP4: "Yann Houry &amp; François Jourde (2026) • CC BY-SA<br />Code source : <a href=\"https://github.com/jourde\" target=\"_blank\" rel=\"noopener noreferrer\">https://github.com/jourde</a>",
    infoP5: "Le sélecteur propose sept cadres : Florimont, Socle commun, GreenComp, DigComp, CRCN, Pix et Pix IA.",
    noData: "Aucune donnée",
    learningDaysLabel: "Jours d'apprentissage",
    learningHoursLabel: "Heures d'apprentissage",
    learningMinutesLabel: "Minutes d'apprentissage",
    designedDaysLabel: "Jours conçus",
    designedHoursLabel: "Heures conçues",
    designedMinutesLabel: "Minutes conçues",
    tabChronology: "Chronologie",
    chronologyTitle: "Chronologie des activités",
    chronologyTooltip: "Vue ordonnée des activités et de leur durée au fil des moments du scénario",
    partitionLinesLabel: "Lignes affichées",
    partitionConfigure: "✎ Configurer",
    partitionConfigTitle: "Configurer les lignes",
    partitionConfigDesc: "Choisissez les lignes à afficher et leur ordre dans la partition.",
    partitionAddLineSection: "Ajouter une ligne",
    partitionAdd: "+ Ajouter",
    partitionMoveUp: "Monter",
    partitionMoveDown: "Descendre",
    partitionShowHide: "Afficher/masquer",
    partitionDeleteLine: "Supprimer cette ligne",
    partitionShowPrefix: "Afficher",
    partitionTypeLocation: "Mode de formation",
    partitionTypeGroup: "Mode groupe",
    partitionTypeSync: "Synchronicité",
    partitionTypeTeaching: "Enseignement",
    partitionTotal: "Total",
    partitionSession: "Session",
    viewGrid: "Grille",
    gridColType: "Type",
    gridColDuration: "Durée",
    gridColLocation: "Lieu",
    gridColGroup: "Groupe",
    gridColSync: "Sync",
    gridColTeaching: "Enseignement",
    gridColEval: "Évaluation",
    gridColAias: "AIAS",
    gridColDesc: "Description",
    gridColInstructions: "Consignes pour les élèves",
    gridColNotes: "Notes",
    gridAddActivity: "+ Activité",
    gridAddSession: "+ Créer un moment",
    gridSessionPrefix: "Séance"
  },
  en: {
    docTitle: "Learning Design Interface - Prototype",
    skipLink: "Skip to main content",
    appTitle: "Learning Design Interface",
    tabSettings: "Settings",
    tabAnalysis: "Analysis",
    collapsePanel: "Collapse panel",
    expandPanel: "Expand panel",
    addMoment: "Add moment",
    addMomentCompact: "Add",
    createMoment: "Create a moment",
    momentAdded: "Moment created.",
    expandNotes: "Expand notes",
    collapseNotes: "Collapse notes",
    hideIntentions: "Hide intentions",
    showIntentions: "Show intentions",
    new: "New",
    import: "Import",
    export: "Export",
    save: "Save",
    saveCopy: "Save a copy",
    share: "Share",
    saved: "Saved",
    savedLocal: "Changes updated.",
    info: "About",
    toolbarRegion: "Design actions",
    analysisTitle: "Learning Experience",
    analysisTooltip: "Visual summary of designed time distributed according to pedagogical choices",
    analysisAiasTitle: "AIAS · Distribution weighted by duration",
    analysisAiasTooltip: "Distribution of designed time according to the level of AI use permitted for activities",
    metaNameLabel: "Title",
    metaNameTooltip: "Name given to the learning scenario",
    metaLearningLabel: "Learning time",
    metaLearningTooltip: "Learning time actually used",
    metaDesignedLabel: "Designed time",
    metaDesignedTooltip: "Duration planned by the teacher when designing the learning scenario",
    metaDayLabel: "1 day =",
    metaDayTooltip: "Number of learning hours counted as one day",
    metaDescriptionLabel: "Description",
    metaDescriptionTooltip: "Overview of the learning scenario and its context",
    metaCommandLabel: "Institutional brief",
    metaCommandTooltip: "Request, framework and constraints defined by the institution behind the scenario",
    metaDeliveryLabel: "Mode",
    metaDeliveryTooltip: "Overall delivery mode: in person, online or blended",
    metaSchoolSystemLabel: "System / classification",
    metaSchoolSystemTooltip: "Education system or international classification used to determine the level",
    metaLevelLabel: "Level",
    metaLevelTooltip: "Education level targeted by the learning scenario",
    metaLevelChooseSystemFirst: "Choose a system or classification first",
    metaLevelPlaceholder: "Choose a level",
    schoolSystemFrance: "France",
    schoolSystemSwitzerland: "Switzerland (HarmoS)",
    schoolSystemUnitedStates: "United States (K–12)",
    schoolSystemBelgiumFrench: "Belgium — French Community",
    schoolSystemBelgiumFlemish: "Belgium — Flemish Community",
    schoolSystemBelgiumGerman: "Belgium — German-speaking Community",
    schoolSystemUnitedKingdomEngland: "United Kingdom — England",
    schoolSystemUnitedKingdomWales: "United Kingdom — Wales",
    schoolSystemUnitedKingdomScotland: "United Kingdom — Scotland",
    schoolSystemUnitedKingdomNorthernIreland: "United Kingdom — Northern Ireland",
    schoolSystemEuropeanSchools: "European Schools system",
    schoolSystemIB: "International Baccalaureate (IB)",
    schoolSystemIsced: "International — ISCED 2011",
    schoolSystemsNationalGroup: "National systems",
    schoolSystemsTransnationalGroup: "Transnational systems",
    schoolSystemsInternationalGroup: "International classification",
    metaSizeLabel: "Group size",
    metaSizeTooltip: "Number of learners covered by the learning scenario",
    metaDesignersLabel: "Designer(s)",
    metaDesignersTooltip: "People who designed the learning scenario",
    metaTrainersLabel: "Teacher(s)",
    metaTrainersTooltip: "People responsible for delivering the learning scenario",
    metaPersonasLabel: "Objectives",
    metaPersonasTooltip: "Overall aims and pedagogical intentions of the course",
    metaSlidersLabel: "Results",
    outcomesLabel: "Learning Outcomes\u00a0*",
    outcomesTooltip: "What learners should know, understand or be able to do after the course. This section is optional.",
    addOutcome: "+ Outcome",
    outcomeTextPlaceholder: "Describe this outcome...",
    deleteOutcome: "Delete outcome",
    changeVerb: "Change verb",
    bloomTitle: "Bloom's Taxonomy",
    bloomSubtitle: "Select a category or an action verb",
    bloomAdd: "Add",
    bloomEdit: "Update",
    unitDays: "days",
    unitHours: "hours",
    unitMinutes: "minutes",
    modeOnsite: "Onsite",
    modeOnline: "Online",
    modeHybrid: "Hybrid",
    activityModeClassroom: "Classroom-based",
    activityModeLocation: "Location-based",
    activityModeOnline: "Online",
    activityModeBlended: "Blended",
    activityModeOther: "Other",
    importTitle: "Import a scenario",
    importModalDesc: "Start from a pre-filled template, or load a file exported from this application. The current design will be replaced.",
    importFromFileTitle: "From my computer",
    importChooseFile: "Choose a file…",
    importDropTitle: "Drag and drop a file here",
    importDropActive: "Drop to import",
    importDropHint: "or choose it from your computer",
    importFileFormats: "LDJ, JSON, CSV, Excel, Markdown",
    importModelsTitle: "Template library",
    importModelsLink: "Browse the library",
    importModelsSearchLabel: "Search for a template",
    importModelsSearchPlaceholder: "Search for a template…",
    importModelsFamilyLabel: "Template family",
    importModelsFamilyAll: "All families",
    importModelsLoading: "Loading templates…",
    importModelsCount: "{count} templates available.",
    importModelsCountOne: "1 template available.",
    importModelsNone: "No template matches this search.",
    importModelsError: "Template library unavailable. File import is still possible.",
    importModelsUnitMoments: "moments",
    importModelsUnitActivities: "activities",
    importModelsToComplete: "To complete:",
    importModelPreviewButton: "Preview",
    importModelUseButton: "Import",
    importModelPreviewEyebrow: "Scenario preview",
    importModelPreviewBack: "Back to templates",
    importModelPreviewLoading: "Loading preview…",
    importModelPreviewError: "This template preview could not be displayed.",
    importModelPreviewObjectives: "Moment objectives",
    importModelPreviewTeacher: "Sequence",
    importModelPreviewStudents: "Student instructions",
    importModelPreviewActivity: "Activity {number}",
    importModelApplied: "Template loaded: {name}",
    importModelFailed: "This template could not be loaded.",
    exportTitle: "Export design",
    exportScopeTitle: "Content to export",
    exportScopeFull: "Full export",
    exportScopeFullDescription: "All the information in the learning design.",
    exportScopeStudents: "Student instructions only",
    exportScopeStudentsDescription: "Sessions, activities, and instructions addressed to students.",
    exportMomentsTitle: "Moments to export",
    exportMomentsAll: "All moments",
    exportMomentsEmpty: "No moments to export.",
    exportMomentsSelection: (selected, total) => `${selected} of ${total} selected`,
    format: "Format",
    exportFilename: "File name",
    exportPreviewCopy: "The exported content is shown below. You can copy it or download the file.",
    exportPreviewTitle: "Preview",
    exportDownloadOnly: "This format is meant to be downloaded. Raw content is not shown or copyable.",
    copy: "Copy",
    download: "Download",
    cancel: "Cancel",
    validate: "Validate",
    close: "Close",
    boardRegion: "Sequence board",
    sessionNotes: "Notes",
    addLearningType: "+ Activity",
    sessionPrefix: "Session",
    viewModeLabel: "Session view mode",
    viewList: "List",
    viewColumns: "Columns",
    sessionMoveHintColumns: "Alt+Left/Right Arrow to move.",
    sessionMoveHintList: "Alt+Up/Down Arrow to move.",
    activityMoveHint: "Alt+Up/Down Arrow to move within the session.",
    sessionTitleLabel: "Session title",
    sessionObjectivesLabel: "Moment objectives",
    sessionActivitiesLabel: "Activities for session",
    activityLabel: "Activity",
    activityDurationLabel: "Activity duration in minutes",
    activityDescriptionLabel: "Activity description",
    activityInstructionsLabel: "Instructions for students",
    activityNotesLabel: "Activity notes",
    sessionNotesLabel: "Session notes",
    duplicateMoment: "Duplicate moment",
    duplicateActivity: "Duplicate activity",
    deleteSession: "Delete session",
    deleteActivity: "Delete activity",
    momentDuplicated: "Moment duplicated just after the original.",
    activityDuplicated: "Activity duplicated just after the original.",
    copySuffix: "copy",
    activityDeleted: "Activity deleted.",
    activityAdded: "Activity added.",
    sessionDeleted: "Session deleted.",
    selectTools: "Select competencies",
    toolPickerTitle: "Select competencies",
    toolPickerFrameworkLabel: "Competency framework",
    toolPickerSource: "View source",
    toolPickerClose: "Close",
    toolsAriaLabel: "Selected competencies",
    removeToolAriaLabel: (name) => `Remove ${name}`,
    toolCount: (n) => n === 1 ? "1 competency" : `${n} competencies`,
    groupTitleType: "Learning type",
    analysisLearningTooltip: "Distribution of designed time across the six learning types",
    groupTitleGroup: "Group",
    analysisGroupTooltip: "Distribution of designed time across individual, subgroup and whole-group work",
    groupTitleTeaching: "Teaching",
    analysisTeachingTooltip: "Distribution of designed time across directed, guided, supported and independent learning",
    groupTitlePacing: "Pacing",
    analysisPacingTooltip: "Distribution of designed time across synchronous and asynchronous activities",
    groupTitleMode: "Mode of delivery",
    analysisDeliveryTooltip: "Distribution of designed time according to where and how activities take place",
    groupTitleEvaluation: "Assessment",
    analysisEvaluationTooltip: "Distribution of designed time according to the planned assessment type",
    aiasFieldLabel: "Role of AI in the task · AIAS 2.1",
    aiasUndecided: "To be defined",
    aiasNotApplicable: "Not applicable",
    aiasPanelIntro: "Choose the role of AI in this task. The levels describe different task designs and are not a hierarchy.",
    aiasLevelsAriaLabel: "AIAS level for the activity",
    aiasAttributionPrefix: "Based on",
    aiasLevelPrefix: "Level",
    aiasUpdated: "Role of AI updated.",
    aiasLevel1Label: "No AI",
    aiasLevel1Description: "The task is completed without AI, in a controlled environment, to assess the student’s own learning.",
    aiasLevel2Label: "AI Planning",
    aiasLevel2Description: "AI may support exploration, research, and planning; the student completes the task independently.",
    aiasLevel3Label: "AI Collaboration",
    aiasLevel3Description: "AI contributes to the work; the student evaluates, modifies, and integrates its outputs.",
    aiasLevel4Label: "Full AI",
    aiasLevel4Description: "AI is fully integrated; the student directs it using critical thinking and subject expertise.",
    aiasLevel5Label: "AI Exploration",
    aiasLevel5Description: "The student explores and co-designs creative uses of AI to produce new ideas or solutions.",
    newActivityDescription: "New activity",
    sessionTitlePlaceholder: "Moment title",
    activityDescriptionPlaceholder: "Describe the activity...",
    activityInstructionsPlaceholder: "Enter the instructions given to students...",
    newDesignConfirm: "Create a new design and replace current content?",
    newDesignModalTitle: "New design",
    newDesignModalMsg: "You are about to create a blank new design. If you have not saved the current design, it will be lost.",
    newDesignModalConfirm: "Create a new design",
    importInvalid: "Invalid file. Import an LDJ, JSON, CSV, Excel or Markdown file exported by this application.",
    commandPlaceholder: "Paste the previously defined institutional brief here...",
    personasPlaceholder: "Describe the learning objectives...",
    slidersPlaceholder: "Describe the expected results...",
    sessionNotesPlaceholder: "Session notes...",
    sessionObjectivesPlaceholder: "Moment objectives...",
    sessionIntentionsLabel: "Pedagogical choices",
    sessionIntentionsPlaceholder: "Pedagogical choices (e.g.: why this order of activities? what alternation of modalities? what rhythm?)",
    activityNotesPlaceholder: "Activity notes...",
    durationMinutesSr: "Duration in minutes",
    fullscreen: "Fullscreen",
    closeFullscreen: "Exit fullscreen",
    markdownToolbarLabel: "Markdown formatting toolbar",
    mdBold: "Bold",
    mdItalic: "Italic",
    mdHeading: "Heading",
    mdList: "Bullet list",
    mdOrderedList: "Numbered list",
    mdQuote: "Quote",
    mdLink: "Link",
    mdPlaceholderBold: "bold text",
    mdPlaceholderItalic: "italic text",
    mdPlaceholderHeading: "Heading",
    mdPlaceholderList: "list item",
    mdPlaceholderOrderedList: "list item",
    mdPlaceholderQuote: "quote",
    mdPlaceholderLinkText: "link text",
    mdPlaceholderLinkUrl: "https://",
    uiLanguage: "Interface language",
    moved: "Item moved.",
    an01: "One or more graphs might not display correctly, because one or more activities do not have a valid duration.",
    an02: "Graphs cannot be computed, because no activity duration is set.",
    an03: "The social learning graph might not display correctly, because one or more learning types do not have group size set.",
    an04: "The “Teaching” graph might be inaccurate, because one or more activities are missing this setting.",
    an05: "The “Pacing” graph might be inaccurate, because one or more activities are missing this setting.",
    an06: "The “Delivery mode” graph might be inaccurate, because one or more activities are missing this setting.",
    an07: "Designed time exceeds declared learning time.",
    an08: "Learning time is not set, but activities have durations.",
    an09: "No learning type set: specify the type of each activity for a meaningful analysis.",
    lt_undefined: "Undefined",
    lt_read: "Read / Watch / Listen",
    lt_investigate: "Investigate",
    lt_practice: "Practice",
    lt_produce: "Produce",
    lt_discuss: "Discuss",
    lt_collaborate: "Collaborate",
    group_whole: "Whole class",
    group_subgroups: "Sub-groups",
    group_individual: "Individual",
    teaching_undefined: "To be defined",
    teaching_directed: "Teacher-directed",
    teaching_directed_description: "The teacher determines what happens at each moment, whether by explaining, demonstrating, or taking pupils through a task step by step. Pupils follow.",
    teaching_guided: "Teacher-guided",
    teaching_guided_description: "Pupils carry out the task; the teacher sets the method and intervenes throughout to prompt, correct and keep the work on track.",
    teaching_supported: "Teacher-supported",
    teaching_supported_description: "Pupils decide how to proceed and set their own pace; the teacher responds when asked or when the work goes astray, but does not direct it.",
    teaching_independent: "Independent work",
    teaching_independent_description: "Pupils determine their own approach, sequence and pace within a task the teacher has designed. No guidance during the work.",
    sync_sync: "Synchronous",
    sync_async: "Asynchronous",
    eval_none: "No assessment",
    eval_diagnostic: "Diagnostic",
    eval_formative: "Formative",
    eval_summative: "Summative",
    eval_certificative: "Certifying",
    infoTitle: "About",
    footerHelp: "Help",
    footerSharedDesigns: "Shared designs",
    infoP1: "This single-page web app is inspired by the UCL Learning Designer:",
    infoP2: "(UCL Knowledge Lab, UCL Institute of Education, 2013-2026).",
    infoP3: "Local processing by default: data stays in your browser unless you sign in and explicitly save a design to your account.",
    infoP4: "Yann Houry &amp; François Jourde (2026) • CC BY-SA<br />Source code: <a href=\"https://github.com/jourde\" target=\"_blank\" rel=\"noopener noreferrer\">https://github.com/jourde</a>",
    infoP5: "The picker includes seven frameworks: Florimont, the French common core, GreenComp, DigComp, CRCN, Pix, and Pix AI.",
    noData: "No data",
    learningDaysLabel: "Learning days",
    learningHoursLabel: "Learning hours",
    learningMinutesLabel: "Learning minutes",
    designedDaysLabel: "Designed days",
    designedHoursLabel: "Designed hours",
    designedMinutesLabel: "Designed minutes",
    tabChronology: "Timeline",
    chronologyTitle: "Activity Timeline",
    chronologyTooltip: "Ordered view of activities and their duration across the scenario's moments",
    partitionLinesLabel: "Displayed lines",
    partitionConfigure: "✎ Configure",
    partitionConfigTitle: "Configure lines",
    partitionConfigDesc: "Choose the lines to display and their order in the partition.",
    partitionAddLineSection: "Add a line",
    partitionAdd: "+ Add",
    partitionMoveUp: "Move up",
    partitionMoveDown: "Move down",
    partitionShowHide: "Show/hide",
    partitionDeleteLine: "Delete this line",
    partitionShowPrefix: "Show",
    partitionTypeLocation: "Mode of delivery",
    partitionTypeGroup: "Group mode",
    partitionTypeSync: "Synchronicity",
    partitionTypeTeaching: "Teaching",
    partitionTotal: "Total",
    partitionSession: "Session",
    viewGrid: "Grid",
    gridColType: "Type",
    gridColDuration: "Duration",
    gridColLocation: "Location",
    gridColGroup: "Group",
    gridColSync: "Sync",
    gridColTeaching: "Teaching",
    gridColEval: "Assessment",
    gridColAias: "AIAS",
    gridColDesc: "Description",
    gridColInstructions: "Instructions for students",
    gridColNotes: "Notes",
    gridAddActivity: "+ Activity",
    gridAddSession: "+ Create a moment",
    gridSessionPrefix: "Session"
  }
};


function normalizeToken(value) {
  return String(value ?? "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
}

return {
  LEARNING_TYPES, ACTIVITY_TYPE_OPTIONS, GROUP_MODE_OPTIONS, TEACHING_OPTIONS,
  UNDEFINED_TEACHING_OPTION, TEACHING_VALUES, SYNC_OPTIONS, LOCATION_OPTIONS,
  LOCATION_VALUES, SCHOOL_SYSTEM_OPTIONS, SCHOOL_LEVEL_OPTIONS, PARTITION_TYPE_OPTIONS,
  EVAL_OPTIONS, AIAS_VERSION, AIAS_LEVELS, AIAS_TRIGGER_ICON, DEFAULT_DAY_HOURS,
  BLOOM_TAXONOMY, I18N, normalizeToken
};
})() };
})();
