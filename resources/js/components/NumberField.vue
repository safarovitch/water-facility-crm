<script setup lang="ts">
/*
 * The one number control for the whole app.
 *
 * It is the stepper the client sees on the profile page when placing an order
 * (−/+ around a centred field), lifted out of RepeatOrderModal so admin forms
 * look and behave the same way. Two non-obvious details:
 *
 * - The field keeps a 16px font on phones. iOS Safari zooms the page in on any
 *   focused control smaller than that and never zooms back out, which is why a
 *   plain `text-sm` input used to leave the UI scaled in. Keep the `text-base`
 *   / `md:text-sm` pair.
 * - `step` is the typing precision (the input's own step), `stepBy` is what the
 *   buttons add. Prices want 0.01 precision but ±1 buttons, so they differ.
 */
import { cn } from '@/lib/utils';
import { Minus, Plus } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number | string | null | undefined;
        min?: number | null;
        max?: number | null;
        /** Typing precision, mapped straight to the input's step attribute. */
        step?: number;
        /** How much the −/+ buttons move. Defaults to step, but never below 1. */
        stepBy?: number | null;
        /** Hide the −/+ buttons for values nobody nudges one at a time. */
        controls?: boolean;
        size?: 'sm' | 'md' | 'lg';
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        id?: string;
        placeholder?: string;
        ariaLabel?: string;
        class?: HTMLAttributes['class'];
        inputClass?: HTMLAttributes['class'];
    }>(),
    {
        min: null,
        max: null,
        step: 1,
        stepBy: null,
        controls: true,
        size: 'md',
        disabled: false,
        readonly: false,
        required: false,
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: number | null): void }>();

const heights: Record<'sm' | 'md' | 'lg', string> = {
    sm: 'h-8',
    md: 'h-9',
    lg: 'h-10',
};

// Local text so the field can sit empty (or mid-typing "1.") without the
// parent seeing a half-parsed number.
const draft = ref(props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue));

watch(
    () => props.modelValue,
    (value) => {
        const incoming = value === null || value === undefined ? '' : String(value);
        if (incoming === '' || draft.value === '') {
            draft.value = incoming;
            return;
        }
        // Same number, different spelling ("2" vs "2.0") means the parent echoed
        // back what is being typed — leave the draft alone so the caret does not
        // jump mid-edit.
        if (Number(incoming) !== Number(draft.value)) {
            draft.value = incoming;
        }
    },
);

const delta = computed(() => props.stepBy ?? Math.max(props.step, 1));

// Decimals come from the step so 0.1 + 0.2 never leaks 0.30000000000000004.
const decimals = computed(() => {
    const parts = String(props.step).split('.');
    return parts.length > 1 ? parts[1].length : 0;
});

const round = (value: number) => Number(value.toFixed(decimals.value));

const clamp = (value: number) => {
    let out = value;
    if (props.min !== null && out < props.min) out = props.min;
    if (props.max !== null && out > props.max) out = props.max;
    return round(out);
};

const current = computed(() => {
    const parsed = Number(draft.value);
    return draft.value === '' || Number.isNaN(parsed) ? null : parsed;
});

const atMin = computed(() => props.min !== null && current.value !== null && current.value <= props.min);
const atMax = computed(() => props.max !== null && current.value !== null && current.value >= props.max);

const push = (value: number | null) => {
    draft.value = value === null ? '' : String(value);
    emit('update:modelValue', value);
};

const onInput = (event: Event) => {
    draft.value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', current.value);
};

// Clamping on blur, not on input, so typing "12" into a max-10 field isn't
// rewritten to "1" after the first keystroke.
const onBlur = () => {
    if (current.value === null) {
        push(null);
        return;
    }
    push(clamp(current.value));
};

const nudge = (direction: 1 | -1) => {
    if (props.disabled || props.readonly) return;
    const base = current.value ?? props.min ?? 0;
    push(clamp(base + direction * delta.value));
};
</script>

<template>
    <div :class="cn('flex items-center gap-1.5', props.class)">
        <button
            v-if="controls && !readonly"
            type="button"
            tabindex="-1"
            :aria-label="'-'"
            :disabled="disabled || atMin"
            @click="nudge(-1)"
            :class="
                cn(
                    'flex shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800',
                    heights[size],
                    size === 'sm' ? 'w-8' : size === 'lg' ? 'w-10' : 'w-9',
                )
            "
        >
            <Minus class="h-4 w-4" />
        </button>

        <input
            :id="id"
            type="number"
            inputmode="decimal"
            :value="draft"
            :min="min ?? undefined"
            :max="max ?? undefined"
            :step="step"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :placeholder="placeholder"
            :aria-label="ariaLabel"
            @input="onInput"
            @blur="onBlur"
            :class="
                cn(
                    'w-full min-w-0 flex-1 rounded-lg border border-slate-200 bg-transparent px-2 text-center text-base font-semibold text-slate-900 transition-colors outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-100',
                    heights[size],
                    inputClass,
                )
            "
        />

        <button
            v-if="controls && !readonly"
            type="button"
            tabindex="-1"
            :aria-label="'+'"
            :disabled="disabled || atMax"
            @click="nudge(1)"
            :class="
                cn(
                    'flex shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800',
                    heights[size],
                    size === 'sm' ? 'w-8' : size === 'lg' ? 'w-10' : 'w-9',
                )
            "
        >
            <Plus class="h-4 w-4" />
        </button>
    </div>
</template>
