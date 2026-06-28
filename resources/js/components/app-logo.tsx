/**
 * Logo-ul oficial Liceul Columna (brandbook). Comută automat:
 *  - extins (sidebar deschis) → lockup orizontal (emblemă + wordmark);
 *  - restrâns (sidebar „icon”) → doar emblema;
 *  - temă luminoasă → varianta navy/verde; temă întunecată → varianta albă.
 * Asset-uri în `public/images/logo/` (prelucrate din brandbook). Linkul către homepage e în componenta-părinte.
 */
export default function AppLogo() {
    return (
        <>
            {/* Lockup orizontal — stare extinsă */}
            <img
                src="/images/logo/columna-horizontal.png"
                alt="Liceul Columna"
                className="h-7 w-auto object-contain object-left dark:hidden group-data-[collapsible=icon]:hidden"
            />
            <img
                src="/images/logo/columna-horizontal-white.png"
                alt="Liceul Columna"
                className="hidden h-7 w-auto object-contain object-left dark:block dark:group-data-[collapsible=icon]:hidden"
            />

            {/* Emblemă — stare restrânsă (icon) */}
            <img
                src="/images/logo/columna-navy.png"
                alt="Liceul Columna"
                className="hidden size-7 object-contain group-data-[collapsible=icon]:block dark:group-data-[collapsible=icon]:hidden"
            />
            <img
                src="/images/logo/columna-white.png"
                alt="Liceul Columna"
                className="hidden size-7 object-contain dark:group-data-[collapsible=icon]:block"
            />
        </>
    );
}
