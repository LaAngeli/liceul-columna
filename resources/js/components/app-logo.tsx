/**
 * Logo-ul oficial Liceul Columna (brandbook). Comută automat:
 *  - extins (sidebar deschis) → lockup orizontal (emblemă + wordmark);
 *  - restrâns (sidebar „icon”) → doar emblema;
 *  - temă luminoasă → varianta navy/verde; temă întunecată → varianta albă.
 *
 * Dimensiuni: 42px înălțime (+50% față de h-7 inițial, decizie de brand); centrat orizontal
 * (`mx-auto` + `object-center`) pentru a arăta echilibrat în header-ul sidebar-ului.
 * Asset-uri în `public/images/logo/` (prelucrate din brandbook). Linkul către homepage e în componenta-părinte.
 */
export default function AppLogo() {
    return (
        <>
            {/* Lockup orizontal — stare extinsă */}
            <img
                src="/images/logo/columna-horizontal.png"
                alt="Liceul Columna"
                className="mx-auto h-[42px] w-auto object-contain object-center dark:hidden group-data-[collapsible=icon]:hidden"
            />
            <img
                src="/images/logo/columna-horizontal-white.png"
                alt="Liceul Columna"
                className="mx-auto hidden h-[42px] w-auto object-contain object-center dark:block dark:group-data-[collapsible=icon]:hidden"
            />

            {/* Emblemă — stare restrânsă (icon) */}
            <img
                src="/images/logo/columna-navy.png"
                alt="Liceul Columna"
                className="mx-auto hidden size-[42px] object-contain group-data-[collapsible=icon]:block dark:group-data-[collapsible=icon]:hidden"
            />
            <img
                src="/images/logo/columna-white.png"
                alt="Liceul Columna"
                className="mx-auto hidden size-[42px] object-contain dark:group-data-[collapsible=icon]:block"
            />
        </>
    );
}
