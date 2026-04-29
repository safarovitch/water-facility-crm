<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'wordmark' | 'stacked' | 'mark' | 'inline' | 'curved';
        accentColor?: string;
        showTagline?: boolean;
    }>(),
    {
        variant: 'wordmark',
        accentColor: '#38bdf8',
        showTagline: true,
    },
);
</script>

<template>
    <!-- MARK only — mountain icon, scales as a square -->
    <svg
        v-if="variant === 'mark'"
        viewBox="0 0 64 64"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="fann"
    >
        <path
            d="M4 50 L20 22 L28 32 L40 14 L54 36 L60 50 Z"
            fill="currentColor"
        />
        <path d="M20 22 L23 26 L17 26 Z M40 14 L43 19 L37 19 Z" fill="white" opacity="0.95" />
    </svg>

    <!-- INLINE — small mountain mark next to the wordmark, single-line, ideal for nav -->
    <svg
        v-else-if="variant === 'inline'"
        viewBox="0 0 220 56"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="fann"
    >
        <g transform="translate(0,4)">
            <path d="M2 38 L14 18 L20 26 L30 12 L40 24 L46 38 Z" fill="currentColor" />
            <path d="M14 18 L17 22 L11 22 Z M30 12 L33 17 L27 17 Z" fill="white" opacity="0.95" />
        </g>
        <text
            x="64"
            y="42"
            font-family="'Fraunces', 'Cormorant Garamond', 'Times New Roman', serif"
            font-size="46"
            font-weight="400"
            font-style="normal"
            letter-spacing="-1.5"
            fill="currentColor"
        >fann</text>
    </svg>

    <!-- CURVED — wordmark + slogan bent along a gentle arc to follow a cylindrical surface -->
    <svg
        v-else-if="variant === 'curved'"
        viewBox="0 0 220 90"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="fann · mountain spring"
        preserveAspectRatio="xMidYMid meet"
    >
        <defs>
            <!-- subtle "frown" arcs: text dips slightly toward the middle, as if hugging the bottle -->
            <path id="fann-arc-word" d="M 18 48 Q 110 58 202 48" fill="none" />
            <path id="fann-arc-slug" d="M 38 78 Q 110 84 182 78" fill="none" />
        </defs>
        <text
            fill="currentColor"
            font-family="'Fraunces', 'Cormorant Garamond', 'Times New Roman', serif"
            font-size="48"
            font-weight="400"
            letter-spacing="-2"
        >
            <textPath href="#fann-arc-word" startOffset="50%" text-anchor="middle">fann</textPath>
        </text>
        <text
            fill="currentColor"
            font-family="'Inter', 'Instrument Sans', system-ui, sans-serif"
            font-size="8"
            font-weight="600"
            letter-spacing="3"
        >
            <textPath href="#fann-arc-slug" startOffset="50%" text-anchor="middle">MOUNTAIN SPRING</textPath>
        </text>
    </svg>

    <!-- WORDMARK — centered, with a tiny peak accent above (premium nav / hero use) -->
    <svg
        v-else-if="variant === 'wordmark'"
        viewBox="0 0 220 96"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="fann"
    >
        <!-- subtle horizontal hairlines flanking a small peak -->
        <line x1="78" y1="16" x2="100" y2="16" stroke="currentColor" stroke-width="0.6" opacity="0.4" />
        <line x1="120" y1="16" x2="142" y2="16" stroke="currentColor" stroke-width="0.6" opacity="0.4" />
        <path d="M104 18 L110 6 L116 18 Z" :fill="accentColor" />
        <text
            x="110"
            y="74"
            text-anchor="middle"
            font-family="'Fraunces', 'Cormorant Garamond', 'Times New Roman', serif"
            font-size="64"
            font-weight="400"
            letter-spacing="-2"
            fill="currentColor"
        >fann</text>
    </svg>

    <!-- STACKED / FULL — mountain silhouette, wordmark, divider, tagline -->
    <svg
        v-else
        viewBox="0 0 320 200"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="fann — mountain spring · Tajikistan"
    >
        <!-- mountain silhouette -->
        <g transform="translate(160,52)">
            <path
                d="M-56 22 L-30 -22 L-16 -2 L 6 -34 L 28 -8 L 52 22 Z"
                fill="currentColor"
            />
            <!-- snow caps -->
            <path
                d="M-30 -22 L-25 -12 L-35 -12 Z M 6 -34 L 12 -22 L 0 -22 Z"
                fill="white"
                opacity="0.96"
            />
            <!-- subtle horizon line under range -->
            <line x1="-72" y1="26" x2="68" y2="26" stroke="currentColor" stroke-width="0.6" opacity="0.35" />
        </g>

        <!-- wordmark -->
        <text
            x="160"
            y="138"
            text-anchor="middle"
            font-family="'Fraunces', 'Cormorant Garamond', 'Times New Roman', serif"
            font-size="80"
            font-weight="400"
            letter-spacing="-3"
            fill="currentColor"
        >fann</text>

        <!-- tagline rule + text -->
        <template v-if="showTagline">
            <line x1="60" y1="160" x2="120" y2="160" stroke="currentColor" stroke-width="0.6" opacity="0.45" />
            <line x1="200" y1="160" x2="260" y2="160" stroke="currentColor" stroke-width="0.6" opacity="0.45" />
            <text
                x="160"
                y="164"
                text-anchor="middle"
                font-family="'Inter', 'Instrument Sans', system-ui, sans-serif"
                font-size="9"
                font-weight="500"
                letter-spacing="6"
                fill="currentColor"
            >MOUNTAIN SPRING</text>
            <text
                x="160"
                y="184"
                text-anchor="middle"
                font-family="'Inter', 'Instrument Sans', system-ui, sans-serif"
                font-size="8"
                font-weight="400"
                letter-spacing="4"
                opacity="0.65"
                fill="currentColor"
            >EST · TAJIKISTAN</text>
        </template>
    </svg>
</template>
