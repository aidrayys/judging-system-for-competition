{{-- Judging System for Competition innovation illustration: large yellow lightbulb with gears, planes, trails and podium.
     viewBox 900x520 - wide composition so the bulb stays dominant on short laptop screens. --}}
<svg class="jc-illustration" viewBox="0 0 900 520" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Innovation illustration: idea lightbulb, gears, paper planes and a podium">
    <defs>
        <linearGradient id="jcBulb" x1="0.22" y1="0" x2="0.78" y2="1">
            <stop offset="0%" stop-color="#FEF9C3"/>
            <stop offset="45%" stop-color="#FDE047"/>
            <stop offset="100%" stop-color="#EAB308"/>
        </linearGradient>
        <linearGradient id="jcNavy" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#3B82F6"/>
            <stop offset="100%" stop-color="#0B1F4D"/>
        </linearGradient>
        <linearGradient id="jcNavySoft" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#93C5FD"/>
            <stop offset="100%" stop-color="#2563EB"/>
        </linearGradient>
        <linearGradient id="jcGold" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#FDE047"/>
            <stop offset="100%" stop-color="#EAB308"/>
        </linearGradient>
        <linearGradient id="jcPodium" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#1D4ED8"/>
            <stop offset="100%" stop-color="#0B1F4D"/>
        </linearGradient>
        <radialGradient id="jcGlow" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0%" stop-color="#FDE047" stop-opacity="0.55"/>
            <stop offset="55%" stop-color="#FDE047" stop-opacity="0.15"/>
            <stop offset="100%" stop-color="#FDE047" stop-opacity="0"/>
        </radialGradient>
        <filter id="jcShadow" x="-30%" y="-30%" width="160%" height="160%">
            <feDropShadow dx="0" dy="14" stdDeviation="16" flood-color="#07143A" flood-opacity="0.16"/>
        </filter>
        <filter id="jcSoftShadow" x="-40%" y="-40%" width="180%" height="180%">
            <feDropShadow dx="0" dy="6" stdDeviation="9" flood-color="#07143A" flood-opacity="0.14"/>
        </filter>
    </defs>

    {{-- atmospheric glow behind the bulb --}}
    <circle cx="450" cy="232" r="250" fill="url(#jcGlow)"/>

    {{-- soft clouds --}}
    <g opacity="0.8">
        <ellipse cx="120" cy="300" rx="86" ry="30" fill="#E2EDFB"/>
        <ellipse cx="182" cy="288" rx="54" ry="26" fill="#EFF5FE"/>
        <ellipse cx="820" cy="300" rx="92" ry="30" fill="#E2EDFB"/>
        <ellipse cx="762" cy="288" rx="48" ry="24" fill="#EFF5FE"/>
    </g>

    {{-- dotted curved trails --}}
    <path d="M18 372 C150 352 236 300 316 250" stroke="#8FB4E0" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="2 13" fill="none"/>
    <path d="M880 210 C800 196 700 190 616 200" stroke="#8FB4E0" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="2 13" fill="none"/>
    <path d="M52 120 C150 92 250 84 330 96" stroke="#EAB308" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 15" fill="none" opacity="0.65"/>

    {{-- navy gear (left) --}}
    <g filter="url(#jcSoftShadow)">
        <path d="M142.3 94.4 L139.9 70.5 L160.1 70.5 L157.7 94.4 L178.3 99.5 L187.3 77.2 L205.2 86.6 L192.0 106.6 L207.9 120.7 L226.1 105.1 L237.6 121.8 L216.6 133.4 L224.1 153.2 L247.5 147.9 L250.0 168.1 L226.0 168.5 L223.4 189.6 L246.6 195.8 L239.4 214.8 L218.0 204.0 L205.9 221.5 L223.6 237.7 L208.4 251.2 L194.4 231.7 L175.6 241.6 L183.6 264.2 L164.0 269.0 L160.6 245.3 L139.4 245.3 L136.0 269.0 L116.4 264.2 L124.4 241.6 L105.6 231.7 L91.6 251.2 L76.4 237.7 L94.1 221.5 L82.0 204.0 L60.6 214.8 L53.4 195.8 L76.6 189.6 L74.0 168.5 L50.0 168.1 L52.5 147.9 L75.9 153.2 L83.4 133.4 L62.4 121.8 L73.9 105.1 L92.1 120.7 L108.0 106.6 L94.8 86.6 L112.7 77.2 L121.7 99.5 Z" fill="url(#jcNavy)"/>
        <circle cx="150" cy="170" r="42" fill="#FFFFFF" opacity="0.94"/>
        <circle cx="150" cy="170" r="19" fill="url(#jcGold)"/>
    </g>

    {{-- gold gear (upper right) --}}
    <g filter="url(#jcSoftShadow)">
        <path d="M728.3 94.4 L725.9 74.5 L744.1 74.5 L741.7 94.4 L759.4 99.6 L768.1 81.6 L783.4 91.4 L770.7 106.9 L782.8 120.8 L799.9 110.4 L807.4 126.9 L788.4 133.0 L791.0 151.3 L811.0 151.7 L808.4 169.7 L789.1 164.5 L781.4 181.3 L798.0 192.5 L786.1 206.3 L772.6 191.5 L757.1 201.5 L765.0 219.8 L747.5 225.0 L744.2 205.2 L725.8 205.2 L722.5 225.0 L705.0 219.8 L712.9 201.5 L697.4 191.5 L683.9 206.3 L672.0 192.5 L688.6 181.3 L680.9 164.5 L661.6 169.7 L659.0 151.7 L679.0 151.3 L681.6 133.0 L662.6 126.9 L670.1 110.4 L687.2 120.8 L699.3 106.9 L686.6 91.4 L701.9 81.6 L710.6 99.6 Z" fill="url(#jcGold)"/>
        <circle cx="735" cy="150" r="28" fill="#FFFFFF" opacity="0.94"/>
        <circle cx="735" cy="150" r="12" fill="#EAB308"/>
    </g>

    {{-- small blue gear (lower right) --}}
    <g filter="url(#jcSoftShadow)">
        <path d="M709.4 387.4 L707.4 373.6 L722.6 373.6 L720.6 387.4 L734.9 392.6 L742.2 380.7 L753.9 390.5 L743.4 399.8 L751.1 413.0 L764.3 408.6 L767.0 423.5 L753.0 423.9 L750.3 439.0 L763.3 444.1 L755.8 457.3 L744.8 448.6 L733.1 458.4 L739.7 470.7 L725.5 475.9 L722.6 462.2 L707.4 462.2 L704.5 475.9 L690.3 470.7 L696.9 458.4 L685.2 448.6 L674.2 457.3 L666.7 444.1 L679.7 439.0 L677.0 423.9 L663.0 423.5 L665.7 408.6 L678.9 413.0 L686.6 399.8 L676.1 390.5 L687.8 380.7 L695.1 392.6 Z" fill="url(#jcNavySoft)"/>
        <circle cx="715" cy="425" r="17" fill="#FFFFFF" opacity="0.94"/>
    </g>

    {{-- paper airplanes --}}
    <g filter="url(#jcSoftShadow)">
        <path d="M596 44 L742 22 L660 152 L626 100 L596 44 Z" fill="#FFFFFF" stroke="#C9DCF3" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M742 22 L626 100 L660 152 L742 22 Z" fill="#E4EFFC"/>
        <path d="M60 44 L140 30 L94 110 L76 76 L60 44 Z" fill="#FFFFFF" stroke="#C9DCF3" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M140 30 L76 76 L94 110 L140 30 Z" fill="#E4EFFC"/>
        <path d="M796 300 L878 284 L826 366 L810 332 L796 300 Z" fill="#FFFFFF" stroke="#C9DCF3" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M878 284 L810 332 L826 366 L878 284 Z" fill="#E4EFFC"/>
    </g>

    {{-- podium / platforms --}}
    <g filter="url(#jcShadow)">
        <rect x="292" y="404" width="126" height="104" rx="20" fill="url(#jcNavySoft)"/>
        <rect x="436" y="342" width="146" height="166" rx="22" fill="url(#jcPodium)"/>
        <rect x="598" y="434" width="116" height="74" rx="18" fill="#B9CDE9"/>
        <rect x="454" y="342" width="110" height="12" rx="6" fill="#FDE047"/>
        <rect x="308" y="404" width="92" height="10" rx="5" fill="#FFFFFF" opacity="0.4"/>
        <rect x="614" y="434" width="82" height="9" rx="4.5" fill="#FFFFFF" opacity="0.55"/>
    </g>

    {{-- blocks & accents on the podium --}}
    <g>
        <rect x="470" y="262" width="54" height="76" rx="12" fill="#FFFFFF" stroke="#D7E2F1" stroke-width="2.5" transform="rotate(-8 497 300)"/>
        <rect x="526" y="280" width="40" height="60" rx="11" fill="#FDE047" transform="rotate(11 546 310)"/>
        <circle cx="336" cy="366" r="20" fill="#FFFFFF" opacity="0.92"/>
        <circle cx="336" cy="366" r="9" fill="#8FB4E0"/>
    </g>

    {{-- main lightbulb --}}
    <g filter="url(#jcShadow)">
        <circle cx="450" cy="190" r="152" fill="url(#jcBulb)"/>
        <circle cx="450" cy="190" r="152" fill="none" stroke="#EAB308" stroke-width="2.5" opacity="0.35"/>
        <path d="M392 108 C428 86 486 84 520 106 C498 134 414 134 392 108 Z" fill="#FFFFFF" opacity="0.5"/>
        <circle cx="404" cy="152" r="17" fill="#FFFFFF" opacity="0.5"/>
        <path d="M418 224 L418 248 L432 248 L432 224 L446 224 L446 248 L460 248 L460 224 L474 224" stroke="#B5820F" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.55"/>
        <path d="M414 288 L486 288 L480 306 L420 306 Z" fill="#C9A227"/>
        <rect x="420" y="306" width="60" height="15" rx="7.5" fill="#B8911F"/>
        <rect x="428" y="324" width="44" height="15" rx="7.5" fill="#9C7A1A"/>
        <path d="M438 340 C438 354 462 354 462 340" stroke="#8A6B16" stroke-width="5" stroke-linecap="round" fill="none"/>
    </g>

    {{-- glow rays around the bulb --}}
    <g stroke="#F0C75A" stroke-width="8" stroke-linecap="round" opacity="0.8">
        <path d="M450 34 L450 2"/>
        <path d="M320 62 L296 34"/>
        <path d="M580 62 L604 34"/>
        <path d="M266 190 L234 190"/>
        <path d="M634 190 L666 190"/>
    </g>

    {{-- leaves --}}
    <g opacity="0.9">
        <path d="M46 476 C82 438 124 432 152 450 C116 484 80 490 46 476 Z" fill="#B7D3C2"/>
        <path d="M46 476 C62 450 86 434 110 426" stroke="#8FB39E" stroke-width="4" stroke-linecap="round" fill="none"/>
        <path d="M832 118 C866 84 906 80 930 96 C896 126 862 128 832 118 Z" fill="#CBE0D2"/>
        <path d="M832 118 C848 96 870 84 892 76" stroke="#8FB39E" stroke-width="4" stroke-linecap="round" fill="none"/>
    </g>

    {{-- floating dots --}}
    <g fill="#B7CEEA">
        <circle cx="330" cy="150" r="7"/>
        <circle cx="560" cy="132" r="5" fill="#E2C15C"/>
        <circle cx="238" cy="352" r="6" fill="#E2C15C"/>
        <circle cx="672" cy="272" r="6"/>
        <circle cx="392" cy="60" r="5"/>
    </g>
</svg>
