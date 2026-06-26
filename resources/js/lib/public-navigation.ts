/**
 * Structura de navigare a site-ului public (columna.md).
 * Sursă unică pentru header, footer și hartă site. Slug-urile păstrează paginile vechi.
 * `tKey` = cheia de traducere (lang/{locale}/site.php); `title` rămâne fallback RO.
 */

export interface NavLink {
    title: string;
    href: string;
    tKey?: string;
}

export interface NavItem {
    title: string;
    href?: string;
    tKey?: string;
    children?: NavLink[];
}

export const mainNav: NavItem[] = [
    {
        title: 'Despre liceu',
        tKey: 'nav.about',
        children: [
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului', tKey: 'about.letter' },
            { title: 'De ce Columna?', href: '/de-ce-columna', tKey: 'about.why' },
            { title: 'Filosofia liceului', href: '/filosofia-liceului', tKey: 'about.philosophy' },
            { title: 'Acreditări', href: '/acreditari', tKey: 'about.accreditation' },
        ],
    },
    {
        title: 'Structura școlii',
        href: '/structura-scolii',
        tKey: 'nav.structure',
        children: [
            { title: 'Școala primară', href: '/scoala-primara', tKey: 'structure.primary' },
            { title: 'Școala gimnazială', href: '/scoala-gimnaziala', tKey: 'structure.gymnasium' },
            { title: 'Școala liceală', href: '/scoala-liceala', tKey: 'structure.lyceum' },
        ],
    },
    { title: 'Personal', href: '/personal', tKey: 'nav.staff' },
    { title: 'Actualități', href: '/actualitati-si-evenimente', tKey: 'nav.news' },
    { title: 'Blog', href: '/blog', tKey: 'nav.blog' },
    {
        title: 'Calendar',
        tKey: 'nav.calendar',
        children: [
            { title: 'Orarul lecțiilor', href: '/orarul-lectiilor', tKey: 'calendar.lessons' },
            { title: 'Orarul sunetelor', href: '/orarul-sunetelor', tKey: 'calendar.bells' },
            { title: 'Orarul examenelor', href: '/orarul-examenelor', tKey: 'calendar.exams' },
            { title: 'Orarul ESS (teze)', href: '/orarul-ess', tKey: 'calendar.ess' },
            { title: 'Orarul pretestărilor', href: '/orarul-pretestarilor', tKey: 'calendar.pretests' },
            { title: 'Pregătire pentru examene', href: '/cursuri-de-pregatire-pentru-examene', tKey: 'calendar.prep' },
            { title: 'Orarul CPAE', href: '/orarul-cpae', tKey: 'calendar.cpae' },
            { title: 'Orar recuperări', href: '/orar-recuperari', tKey: 'calendar.recovery' },
            { title: 'Ședințele cu părinții', href: '/sedintele-cu-parintii', tKey: 'calendar.meetings' },
        ],
    },
    { title: 'Galerie', href: '/galerie', tKey: 'nav.gallery' },
    { title: 'Admitere', href: '/admitere', tKey: 'nav.admission' },
];

/** Meniu secundar (bara de sus). */
export const utilityNav: NavLink[] = [
    { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala', tKey: 'utility.cei' },
    { title: 'CPAE', href: '/extracurriculare', tKey: 'utility.cpae' },
    { title: 'Consiliul Metodic', href: '/consiliul-metodic', tKey: 'utility.methodical' },
    { title: 'Cambridge English', href: '/cambridge-english-exam', tKey: 'utility.cambridge' },
    { title: 'Biblioteca online', href: '/biblioteca-online', tKey: 'utility.library' },
    { title: 'Tabără de vară', href: '/tabara-de-vara', tKey: 'utility.summer_camp' },
    { title: 'Contacte', href: '/contacte', tKey: 'utility.contact' },
];

/** Coloane footer. */
export const footerNav: { title: string; tKey: string; links: NavLink[] }[] = [
    {
        title: 'Despre liceu',
        tKey: 'footer.about',
        links: [
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului', tKey: 'about.letter' },
            { title: 'De ce Columna?', href: '/de-ce-columna', tKey: 'about.why' },
            { title: 'Filosofia liceului', href: '/filosofia-liceului', tKey: 'about.philosophy' },
            { title: 'Acreditări', href: '/acreditari', tKey: 'about.accreditation' },
        ],
    },
    {
        title: 'Pentru elevi și părinți',
        tKey: 'footer.students',
        links: [
            { title: 'Admitere', href: '/admitere', tKey: 'nav.admission' },
            { title: 'Orarul lecțiilor', href: '/orarul-lectiilor', tKey: 'calendar.lessons' },
            { title: 'Biblioteca online', href: '/biblioteca-online', tKey: 'utility.library' },
            { title: 'Pregătire pentru examene', href: '/cursuri-de-pregatire-pentru-examene', tKey: 'calendar.prep' },
            { title: 'Ședințele cu părinții', href: '/sedintele-cu-parintii', tKey: 'calendar.meetings' },
        ],
    },
    {
        title: 'Activitate',
        tKey: 'footer.activity',
        links: [
            { title: 'Actualități/Evenimente', href: '/actualitati-si-evenimente', tKey: 'nav.news' },
            { title: 'Blog', href: '/blog', tKey: 'nav.blog' },
            { title: 'Galerie', href: '/galerie', tKey: 'nav.gallery' },
            { title: 'CPAE', href: '/extracurriculare', tKey: 'utility.cpae' },
            { title: 'Tabără de vară', href: '/tabara-de-vara', tKey: 'utility.summer_camp' },
            { title: 'Sponsorizare', href: '/sponsorizare', tKey: 'utility.sponsorship' },
        ],
    },
    {
        title: 'Instituție',
        tKey: 'footer.institution',
        links: [
            { title: 'Structura școlii', href: '/structura-scolii', tKey: 'nav.structure' },
            { title: 'Personal', href: '/personal', tKey: 'nav.staff' },
            { title: 'Consiliul Metodic', href: '/consiliul-metodic', tKey: 'utility.methodical' },
            { title: 'Consiliul școlar', href: '/consiliul-scolar', tKey: 'utility.council' },
            { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala', tKey: 'utility.cei' },
            { title: 'Contacte', href: '/contacte', tKey: 'utility.contact' },
        ],
    },
];

export const siteContact = {
    name: 'IPL „Liceul Columna”',
    address: 'str. Alba Iulia 5/2, Chișinău, Republica Moldova',
    phone: '(+373) 22 74 28 52',
    email: 'info@columna.org.md',
    tagline: 'Studii de CALITATE pentru un viitor de CALITATE',
};
