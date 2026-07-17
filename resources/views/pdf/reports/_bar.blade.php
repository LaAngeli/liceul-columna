{{-- O bară orizontală (grafic simplu, robust în mpdf: bgcolor + &nbsp; — celulele goale ar colapsa).
     Props: $label, $valueLabel, $percent (0–100), $color (null=navy / green / red / amber). --}}
@php($barHex = match ($color ?? null) {
    'green' => '#9bc31e',
    'red' => '#b3261e',
    'amber' => '#d97706',
    default => '#0f4d77',
})
<tr>
    <td class="bar-label">{{ $label }}</td>
    <td class="bar-track">
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td width="{{ max(1, min(100, (int) $percent)) }}%" style="background: {{ $barHex }}; font-size: 2pt; line-height: 9px; padding: 0; border: none;">&nbsp;</td>
                {{-- La 100% restul NU se randează: un &nbsp; suplimentar ar depăși 100% și mpdf ar comprima bara. --}}
                @if ((int) $percent < 100)
                    <td style="background: #eef2f6; font-size: 2pt; line-height: 9px; padding: 0; border: none;">&nbsp;</td>
                @endif
            </tr>
        </table>
    </td>
    <td class="bar-value">{{ $valueLabel }}</td>
</tr>
