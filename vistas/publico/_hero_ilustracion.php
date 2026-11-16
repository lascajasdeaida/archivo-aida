<?php // Ilustración del hero (SVG propio de Diseño web, copiado de docs/diseno/maquetas/inicio.html). Decorativa. ?>
<svg class="hero__ilustracion" viewBox="0 0 460 440" aria-hidden="true" focusable="false">
        <defs>
          <pattern id="perfo" width="22" height="28" patternUnits="userSpaceOnUse"><rect x="6" y="7" width="10" height="13" rx="2" fill="#3A2A1F"/></pattern>
          <clipPath id="clip-film"><rect x="0" y="0" width="520" height="96"/></clipPath>
        </defs>
        <!-- rollo de film -->
        <g transform="translate(-40 300) rotate(-14)">
          <rect width="540" height="96" fill="#DD7F2C" opacity=".9"/>
          <rect width="540" height="96" fill="url(#perfo)" opacity="1" clip-path="url(#clip-film)" transform="translate(0 0)"/>
          <rect y="68" width="540" height="28" fill="url(#perfo)"/>
          <g fill="#EA9A3E" stroke="#3A2A1F" stroke-width="3">
            <rect x="14" y="26" width="92" height="44"/><rect x="118" y="26" width="92" height="44"/><rect x="222" y="26" width="92" height="44"/><rect x="326" y="26" width="92" height="44"/><rect x="430" y="26" width="92" height="44"/>
          </g>
          <g fill="none" stroke="#3A2A1F" stroke-width="2" opacity=".55">
            <path d="M30 60l18-20 14 14 10-8 22 14"/><circle cx="172" cy="44" r="8"/><path d="M236 62h64M246 52h44"/><path d="M340 60c14-22 30-22 44 0"/>
          </g>
        </g>
        <!-- hoja mecanografiada -->
        <g transform="translate(150 24) rotate(6)">
          <rect width="250" height="320" fill="#F3EAD9" />
          <rect width="250" height="320" fill="none" stroke="#3A2A1F" stroke-opacity=".25"/>
          <g fill="#281D15" opacity=".72">
            <rect x="26" y="34" width="96" height="7"/>
            <rect x="26" y="66" width="196" height="4"/><rect x="26" y="80" width="188" height="4"/><rect x="26" y="94" width="200" height="4"/><rect x="26" y="108" width="150" height="4"/>
            <rect x="26" y="134" width="196" height="4"/><rect x="26" y="148" width="180" height="4"/><rect x="26" y="162" width="198" height="4"/><rect x="26" y="176" width="120" height="4"/>
            <rect x="26" y="202" width="190" height="4"/><rect x="26" y="216" width="200" height="4"/><rect x="26" y="230" width="84" height="4"/>
          </g>
          <!-- corrección a mano -->
          <g fill="none" stroke="#A14B14" stroke-width="2.4" stroke-linecap="round">
            <path d="M110 92c20-6 50-4 70 4"/><path d="M134 140c-12 18 4 34 26 26s34-28 58-14"/><path d="M28 262c24-14 42 10 64-4s30-10 46 2"/>
          </g>
        </g>
        <!-- teclas de máquina de escribir -->
        <g transform="translate(40 30)">
          <g fill="#3A2A1F" stroke="#EA9A3E" stroke-width="2.5">
            <circle cx="20" cy="20" r="17"/><circle cx="62" cy="20" r="17"/><circle cx="104" cy="20" r="17"/>
            <circle cx="41" cy="60" r="17"/><circle cx="83" cy="60" r="17"/>
          </g>
          <g font-family="Courier Prime, monospace" font-size="17" font-weight="700" fill="#F3EAD9" text-anchor="middle">
            <text x="20" y="26">Q</text><text x="62" y="26">W</text><text x="104" y="26">E</text><text x="41" y="66">A</text><text x="83" y="66">S</text>
          </g>
        </g>
      </svg>
