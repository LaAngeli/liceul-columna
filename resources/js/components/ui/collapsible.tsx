import * as CollapsiblePrimitive from "@radix-ui/react-collapsible"

import { cn } from "@/lib/utils"

function Collapsible({
  ...props
}: React.ComponentProps<typeof CollapsiblePrimitive.Root>) {
  return <CollapsiblePrimitive.Root data-slot="collapsible" {...props} />
}

function CollapsibleTrigger({
  ...props
}: React.ComponentProps<typeof CollapsiblePrimitive.CollapsibleTrigger>) {
  return (
    <CollapsiblePrimitive.CollapsibleTrigger
      data-slot="collapsible-trigger"
      {...props}
    />
  )
}

function CollapsibleContent({
  className,
  ...props
}: React.ComponentProps<typeof CollapsiblePrimitive.CollapsibleContent>) {
  // Înălțimea e animată pe variabila Radix (--radix-collapsible-content-height); Radix ține
  // conținutul montat până se termină animația de închidere.
  //
  // Curba: `cubic-bezier(0.4, 0, 0.2, 1)` (standard, simetrică), NU curba de tip panou glisant
  // `cubic-bezier(0.32, 0.72, 0, 1)`. Aceea pornește exploziv — măsurat, muta lista de dedesubt
  // cu ~48px într-un singur cadru, ceea ce se percepe ca o zvâcnire a rândurilor. Cu pornire lină,
  // deplasarea maximă pe cadru scade sub ~12px și mișcarea devine continuă.
  return (
    <CollapsiblePrimitive.CollapsibleContent
      data-slot="collapsible-content"
      className={cn(
        "overflow-hidden",
        "data-[state=open]:animate-[collapsible-down_260ms_cubic-bezier(0.4,0,0.2,1)]",
        "data-[state=closed]:animate-[collapsible-up_220ms_cubic-bezier(0.4,0,0.2,1)]",
        "motion-reduce:animate-none",
        className,
      )}
      {...props}
    />
  )
}

export { Collapsible, CollapsibleTrigger, CollapsibleContent }
