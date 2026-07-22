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
  // Fără animație, expandarea muta BRUSC rândurile de sub secțiune (salt vizual pe iconițe).
  // Înălțimea e animată pe variabila Radix (--radix-collapsible-content-height), cu aceeași
  // curbă ca meniul mobil; Radix ține conținutul montat până se termină animația de închidere.
  return (
    <CollapsiblePrimitive.CollapsibleContent
      data-slot="collapsible-content"
      className={cn(
        "overflow-hidden",
        "data-[state=open]:animate-[collapsible-down_240ms_cubic-bezier(0.32,0.72,0,1)]",
        "data-[state=closed]:animate-[collapsible-up_200ms_cubic-bezier(0.32,0.72,0,1)]",
        "motion-reduce:animate-none",
        className,
      )}
      {...props}
    />
  )
}

export { Collapsible, CollapsibleTrigger, CollapsibleContent }
