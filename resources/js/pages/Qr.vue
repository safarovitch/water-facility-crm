<script setup lang="ts">
import FannLogo from '@/components/FannLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import WhatsAppIcon from '@/components/WhatsAppIcon.vue';
import { useLandingI18n } from '@/composables/useLandingI18n';
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Package, Phone, Send } from 'lucide-vue-next';

const { t } = useLandingI18n();

const phoneHref = 'tel:+992178605005';
const whatsappHref = 'https://wa.me/992178605005';
const telegramHref = 'https://t.me/fannwaterbot';
</script>

<template>
    <Head title="Fann — Mountain Spring Water · 19L Delivery in Tajikistan">
        <meta name="description" content="Fann · Pure 19L mountain spring water from the Varzob valley, delivered to homes and offices across Tajikistan year-round." />
    </Head>

    <div class="flex min-h-screen flex-col bg-[#f6f6f6] text-slate-900 antialiased">
        <header class="flex items-center justify-between px-5 py-4">
            <Link href="/" class="flex items-center text-slate-900">
                <FannLogo variant="inline" class="h-8 w-auto" />
            </Link>
            <LanguageSwitcher />
        </header>

        <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-5 pb-10">
            <div class="text-center">
                <h1 class="text-2xl font-semibold tracking-tight text-sky-600 sm:text-3xl">
                    {{ t('qr.title') }}
                </h1>
                <p class="mt-2 text-sm text-slate-600 sm:text-base">
                    {{ t('qr.subtitle') }}
                </p>
            </div>

            <div class="mt-8 flex flex-col gap-3">
                <a
                    :href="phoneHref"
                    class="inline-flex h-14 w-full items-center justify-center gap-3 rounded-full bg-sky-500 text-base font-semibold text-white shadow-md transition hover:bg-sky-600"
                >
                    <Phone class="h-5 w-5" />
                    {{ t('qr.orderPhone') }}
                </a>
                <a
                    :href="telegramHref"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-14 w-full items-center justify-center gap-3 rounded-full bg-[#229ED9] text-base font-semibold text-white shadow-md transition hover:bg-[#1c8ec2]"
                >
                    <Send class="h-5 w-5" />
                    {{ t('qr.orderTelegram') }}
                </a>
                <a
                    :href="whatsappHref"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-14 w-full items-center justify-center gap-3 rounded-full bg-[#25D366] text-base font-semibold text-white shadow-md transition hover:bg-[#1ebe5b]"
                >
                    <WhatsAppIcon class="h-5 w-5" />
                    {{ t('qr.orderWhatsapp') }}
                </a>
                <Link
                    href="/profile"
                    class="inline-flex h-14 w-full items-center justify-center gap-3 rounded-full bg-slate-900 text-base font-semibold text-white shadow-md transition hover:bg-slate-800"
                >
                    <Package class="h-5 w-5" />
                    {{ t('qr.myOrders') }}
                </Link>
            </div>

            <div class="mt-8 text-center">
                <Link
                    href="/"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-6 text-sm font-medium text-slate-700 transition hover:border-slate-400"
                >
                    <Globe class="h-4 w-4" />
                    {{ t('qr.visitWebsite') }}
                </Link>
            </div>
        </main>
    </div>
</template>
