/**
 * Structura de navigare a site-ului public (columna.md).
 * Sursă unică pentru header, footer și hartă site. Slug-urile păstrează paginile vechi.
 */

export interface NavLink {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: string;
    children?: NavLink[];
}

export const mainNav: NavItem[] = [
    {
        title: 'Despre liceu',
        children: [
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului' },
            { title: 'De ce Columna?', href: '/de-ce-columna' },
            { title: 'Filosofia liceului', href: '/filosofia-liceului' },
            { title: 'Acreditări', href: '/acreditari' },
        ],
    },
    {
        title: 'Structura școlii',
        href: '/structura-scolii',
        children: [
            { title: 'Școala primară', href: '/scoala-primara' },
            { title: 'Școala gimnazială', href: '/scoala-gimnaziala' },
            { title: 'Școala liceală', href: '/scoala-liceala' },
        ],
    },
    { title: 'Personal', href: '/personal' },
    { title: 'Actualități', href: '/actualitati-si-evenimente' },
    { title: 'Blog', href: '/blog' },
    {
        title: 'Calendar',
        children: [
            { title: 'Orarul lecțiilor', href: '/orarul-lectiilor' },
            { title: 'Orarul sunetelor', href: '/orarul-sunetelor' },
            { title: 'Orarul examenelor', href: '/orarul-examenelor' },
            { title: 'Orarul ESS (teze)', href: '/orarul-ess' },
            { title: 'Orarul pretestărilor', href: '/orarul-pretestarilor' },
            { title: 'Pregătire pentru examene', href: '/cursuri-de-pregatire-pentru-examene' },
            { title: 'Orarul CPAE', href: '/orarul-cpae' },
            { title: 'Orar recuperări', href: '/orar-recuperari' },
            { title: 'Ședințele cu părinții', href: '/sedintele-cu-parintii' },
        ],
    },
    { title: 'Galerie', href: '/galerie' },
    { title: 'Admitere', href: '/admitere' },
    { title: 'Autorizare', href: '/autorizare' },
];

/** Meniu secundar (bara de sus). */
export const utilityNav: NavLink[] = [
    { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala' },
    { title: 'CPAE', href: '/extracurriculare' },
    { title: 'Consiliul Metodic', href: '/consiliul-metodic' },
    { title: 'Cambridge English', href: '/cambridge-english-exam' },
    { title: 'Biblioteca online', href: '/biblioteca-online' },
    { title: 'Tabără de vară', href: '/tabara-de-vara' },
    { title: 'Contacte', href: '/contacte' },
];

/** Coloane footer. */
export const footerNav: { title: string; links: NavLink[] }[] = [
    {
        title: 'Despre liceu',
        links: [
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului' },
            { title: 'De ce Columna?', href: '/de-ce-columna' },
            { title: 'Filosofia liceului', href: '/filosofia-liceului' },
            { title: 'Acreditări', href: '/acreditari' },
            { title: 'Autorizare', href: '/autorizare' },
        ],
    },
    {
        title: 'Pentru elevi și părinți',
        links: [
            { title: 'Admitere', href: '/admitere' },
            { title: 'Orarul lecțiilor', href: '/orarul-lectiilor' },
            { title: 'Biblioteca online', href: '/biblioteca-online' },
            { title: 'Pregătire pentru examene', href: '/cursuri-de-pregatire-pentru-examene' },
            { title: 'Ședințele cu părinții', href: '/sedintele-cu-parintii' },
        ],
    },
    {
        title: 'Activitate',
        links: [
            { title: 'Actualități/Evenimente', href: '/actualitati-si-evenimente' },
            { title: 'Blog', href: '/blog' },
            { title: 'Galerie', href: '/galerie' },
            { title: 'CPAE', href: '/extracurriculare' },
            { title: 'Tabără de vară', href: '/tabara-de-vara' },
            { title: 'Sponsorizare', href: '/sponsorizare' },
        ],
    },
    {
        title: 'Instituție',
        links: [
            { title: 'Structura școlii', href: '/structura-scolii' },
            { title: 'Personal', href: '/personal' },
            { title: 'Consiliul Metodic', href: '/consiliul-metodic' },
            { title: 'Consiliul școlar', href: '/consiliul-scolar' },
            { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala' },
            { title: 'Contacte', href: '/contacte' },
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
