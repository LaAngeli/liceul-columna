/**
 * Arhitectura informațională a site-ului public (columna.md) — restructurată („Columna Civic Editorial").
 * 5 grupuri principale: Despre noi · Programe · Admitere · Viața școlii · Contacte.
 * Sursă unică pentru header, footer și hartă site. `tKey` = cheie i18n (lang/{locale}/site.php).
 * NOTĂ: paginile net-noi (istorie, taxe, întrebări frecvente, hub Calendar) se adaugă pe măsură ce
 * sunt construite — momentan link-urile țintesc rutele existente, ca să nu apară 404.
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
        title: 'Despre noi',
        tKey: 'menu.about',
        children: [
            { title: 'De ce Columna?', href: '/de-ce-columna', tKey: 'about.why' },
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului', tKey: 'about.letter' },
            { title: 'Misiune și valori', href: '/filosofia-liceului', tKey: 'menu.mission' },
            { title: 'Istoria liceului', href: '/istorie', tKey: 'menu.history' },
            { title: 'Personal didactic', href: '/personal', tKey: 'nav.staff' },
            { title: 'Acreditări și autorizare', href: '/acreditari', tKey: 'about.accreditation' },
            { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala', tKey: 'utility.cei' },
            { title: 'Consiliul Metodic', href: '/consiliul-metodic', tKey: 'utility.methodical' },
            { title: 'Consiliul școlar', href: '/consiliul-scolar', tKey: 'utility.council' },
        ],
    },
    {
        title: 'Programe de studii',
        href: '/structura-scolii',
        tKey: 'menu.programs',
        children: [
            { title: 'Structura școlii', href: '/structura-scolii', tKey: 'nav.structure' },
            { title: 'Școala primară', href: '/scoala-primara', tKey: 'structure.primary' },
            { title: 'Școala gimnazială', href: '/scoala-gimnaziala', tKey: 'structure.gymnasium' },
            { title: 'Școala liceală', href: '/scoala-liceala', tKey: 'structure.lyceum' },
            { title: 'Cambridge English', href: '/cambridge-english-exam', tKey: 'utility.cambridge' },
            { title: 'Activități extracurriculare', href: '/extracurriculare', tKey: 'menu.extracurricular' },
            { title: 'Tabăra de vară', href: '/tabara-de-vara', tKey: 'utility.summer_camp' },
        ],
    },
    {
        title: 'Admitere',
        href: '/admitere',
        tKey: 'nav.admission',
        children: [
            { title: 'Procesul de admitere', href: '/admitere', tKey: 'menu.admission_process' },
            { title: 'Programează o vizită', href: '/programeaza-vizita', tKey: 'menu.book_visit' },
            { title: 'Înscriere online', href: '/inregistrarea-student', tKey: 'menu.enroll_online' },
            { title: 'Taxe și costuri', href: '/taxe', tKey: 'menu.fees' },
            { title: 'Întrebări frecvente', href: '/intrebari-frecvente', tKey: 'menu.faq' },
        ],
    },
    {
        title: 'Viața școlii',
        tKey: 'menu.school_life',
        children: [
            { title: 'Actualități și evenimente', href: '/actualitati-si-evenimente', tKey: 'nav.news' },
            { title: 'Blog', href: '/blog', tKey: 'nav.blog' },
            { title: 'Galerie foto', href: '/galerie', tKey: 'nav.gallery' },
            { title: 'Calendar și orare', href: '/calendar', tKey: 'menu.calendar' },
            { title: 'Biblioteca online', href: '/biblioteca-online', tKey: 'utility.library' },
        ],
    },
    { title: 'Contacte', href: '/contacte', tKey: 'utility.contact' },
];

/** Meniu secundar (bara de sus). */
export const utilityNav: NavLink[] = [
    { title: 'Calendar și orare', href: '/calendar', tKey: 'menu.calendar' },
    { title: 'Biblioteca online', href: '/biblioteca-online', tKey: 'utility.library' },
    { title: 'Sponsorizare', href: '/sponsorizare', tKey: 'utility.sponsorship' },
];

/** Coloane footer — 5 grupuri (Despre · Programe · Admitere · Resurse · Instituție). */
export const footerNav: { title: string; tKey: string; links: NavLink[] }[] = [
    {
        title: 'Despre liceu',
        tKey: 'footer.about',
        links: [
            { title: 'De ce Columna?', href: '/de-ce-columna', tKey: 'about.why' },
            { title: 'Scrisoarea directorului', href: '/scrisoarea-directorului', tKey: 'about.letter' },
            { title: 'Misiune și valori', href: '/filosofia-liceului', tKey: 'menu.mission' },
            { title: 'Personal didactic', href: '/personal', tKey: 'nav.staff' },
            { title: 'Acreditări', href: '/acreditari', tKey: 'about.accreditation' },
        ],
    },
    {
        title: 'Programe',
        tKey: 'menu.programs',
        links: [
            { title: 'Școala primară', href: '/scoala-primara', tKey: 'structure.primary' },
            { title: 'Școala gimnazială', href: '/scoala-gimnaziala', tKey: 'structure.gymnasium' },
            { title: 'Școala liceală', href: '/scoala-liceala', tKey: 'structure.lyceum' },
            { title: 'Cambridge English', href: '/cambridge-english-exam', tKey: 'utility.cambridge' },
            { title: 'Activități extracurriculare', href: '/extracurriculare', tKey: 'menu.extracurricular' },
        ],
    },
    {
        title: 'Admitere',
        tKey: 'nav.admission',
        links: [
            { title: 'Procesul de admitere', href: '/admitere', tKey: 'menu.admission_process' },
            { title: 'Programează o vizită', href: '/programeaza-vizita', tKey: 'menu.book_visit' },
            { title: 'Înscriere online', href: '/inregistrarea-student', tKey: 'menu.enroll_online' },
            { title: 'Tabăra de vară', href: '/tabara-de-vara', tKey: 'utility.summer_camp' },
        ],
    },
    {
        title: 'Resurse',
        tKey: 'menu.resources',
        links: [
            { title: 'Actualități și evenimente', href: '/actualitati-si-evenimente', tKey: 'nav.news' },
            { title: 'Blog', href: '/blog', tKey: 'nav.blog' },
            { title: 'Galerie foto', href: '/galerie', tKey: 'nav.gallery' },
            { title: 'Biblioteca online', href: '/biblioteca-online', tKey: 'utility.library' },
        ],
    },
    {
        title: 'Instituție',
        tKey: 'footer.institution',
        links: [
            { title: 'Centrul de Evaluare Instituțională', href: '/centrul-de-evaluare-institutionala', tKey: 'utility.cei' },
            { title: 'Consiliul Metodic', href: '/consiliul-metodic', tKey: 'utility.methodical' },
            { title: 'Consiliul școlar', href: '/consiliul-scolar', tKey: 'utility.council' },
            { title: 'Sponsorizare', href: '/sponsorizare', tKey: 'utility.sponsorship' },
            { title: 'Contacte', href: '/contacte', tKey: 'utility.contact' },
        ],
    },
];

export const siteContact = {
    name: 'IPL „Liceul Columna”',
    address: 'str. Alba Iulia 5/2, Chișinău, Republica Moldova',
    phone: '(+373) 22 74 28 52',
    email: 'info@columna.org.md',
    tagline: 'Succesul copilului începe aici.',
    founded: 1998,
};
