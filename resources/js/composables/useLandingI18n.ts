import { ref, computed } from 'vue';

export type Locale = 'en' | 'ru' | 'tg';

export const SUPPORTED_LOCALES: { code: Locale; label: string; short: string }[] = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ru', label: 'Русский', short: 'RU' },
  // Tajik translations remain in the dictionary below but the locale is hidden
  // from the switcher and auto-detect for now. Re-enable by restoring this row.
  // { code: 'tg', label: 'Тоҷикӣ', short: 'TJ' },
];

const STORAGE_KEY = 'fann.landing.locale';

function detectInitialLocale(): Locale {
  if (typeof window === 'undefined') return 'en';
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY) as Locale | null;
    if (stored && SUPPORTED_LOCALES.some((l) => l.code === stored)) return stored;
  } catch {
    /* localStorage may be unavailable (private mode, SSR) */
  }
  const browser = (navigator.language || (navigator.languages?.[0] ?? 'en')).toLowerCase();
  if (browser.startsWith('ru')) return 'ru';
  // Tajik auto-detect is disabled for now — visitors with tg/tj locales fall
  // through to English. Re-enable here and in SUPPORTED_LOCALES above when ready.
  return 'en';
}

const locale = ref<Locale>(detectInitialLocale());

// Singleton reactive state shared across components on the page.
export function useLandingI18n() {
  const setLocale = (next: Locale) => {
    locale.value = next;
    try {
      window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
      /* ignore */
    }
    if (typeof document !== 'undefined') {
      document.documentElement.lang = next === 'tg' ? 'tg' : next;
    }
  };

  const dict = computed(() => translations[locale.value] ?? translations.en);

  const t = (key: string): string => {
    const segments = key.split('.');
    let cursor: any = dict.value;
    for (const seg of segments) {
      if (cursor && typeof cursor === 'object' && seg in cursor) {
        cursor = cursor[seg];
      } else {
        // Fallback to English if key missing in current locale.
        cursor = segments.reduce<any>((acc, s) => (acc && s in acc ? acc[s] : undefined), translations.en);
        break;
      }
    }
    return typeof cursor === 'string' ? cursor : key;
  };

  return { locale, setLocale, t, supportedLocales: SUPPORTED_LOCALES };
}

type Dictionary = Record<string, any>;

const en: Dictionary = {
  nav: {
    about: 'About',
    whyUs: 'Why us',
    howItWorks: 'How it works',
    pricing: 'Pricing',
    delivery: 'Delivery',
    contact: 'Contact',
    dashboard: 'Dashboard',
    login: 'Log in',
    signup: 'Sign up',
    order: 'Order now',
    orderShort: 'Order',
  },
  hero: {
    badge: 'fann · sourced in Varzob, Tajikistan',
    headline1: 'Pure mountain spring water,',
    headline2: 'delivered to your door.',
    subtitle:
      '19-litre bottles of natural Varzob valley spring water, professionally filtered and brought to homes and offices across Tajikistan — all year round.',
    callToOrder: 'Call to order',
    whatsapp: 'Order on WhatsApp',
    statBottleLabel: 'Bottle size',
    statBottleValue: '19 L',
    statDeliveryLabel: 'Delivery',
    statDeliveryValue: 'Year-round',
    statSourceLabel: 'Source',
    statSourceValue: 'Varzob',
  },
  about: {
    eyebrow: 'Our source',
    title: 'From the Varzob valley.',
    body:
      'Our water is collected in the Varzob gorge, where the Hisor mountains feed deep, natural springs with snow-melt that has been filtering through stone for years. We bottle it close to the source to preserve its mineral profile and crisp taste.',
    bullet1: 'Naturally cold, naturally clean — no chemical treatment required.',
    bullet2: 'Bottled in a sealed, food-grade facility under strict hygiene controls.',
    bullet3: 'Delivered in reusable, sanitised 19-litre bottles.',
    locationLabel: 'Varzob Gorge, Tajikistan',
    locationFacts: 'Spring elevation: ~1 600 m. Average source temperature: 6–8 °C.',
  },
  features: {
    eyebrow: 'Why us',
    title: 'Clean water shouldn’t be complicated.',
    body:
      'We do one thing: bring real mountain spring water to your home or office, reliably, and at a fair price.',
    sourceTitle: 'Mountain Spring Source',
    sourceDesc:
      'Bottled directly at the source in the Varzob valley, where Tajikistan’s alpine snow-melt feeds pure underground springs.',
    filtrationTitle: 'Multi-Stage Filtration',
    filtrationDesc:
      'Mechanical, carbon and UV filtration preserve natural minerals while removing every impurity.',
    deliveryTitle: 'Free Home & Office Delivery',
    deliveryDesc:
      'Year-round delivery across Dushanbe and surrounding districts. Schedule once — we keep you stocked.',
    mineralsTitle: 'Naturally Balanced Minerals',
    mineralsDesc:
      'Light, soft taste with the calcium, magnesium and potassium your body actually needs.',
  },
  how: {
    eyebrow: 'How it works',
    title: 'Three steps.',
    step1Title: 'Place an order',
    step1Text: 'Call, WhatsApp or send a quick message — tell us how many bottles and where.',
    step2Title: 'We deliver',
    step2Text: 'Same-day or next-day delivery to your home or office, no minimum order.',
    step3Title: 'Stay hydrated',
    step3Text: 'We pick up empties on the next visit. You only ever pay for the water.',
  },
  pricing: {
    eyebrow: 'Pricing',
    title: 'Simple, transparent pricing.',
    body:
      'Pay per bottle or set up a regular delivery — whatever suits your home or office best.',
    popular: 'Popular',
    disclaimer: 'Prices are indicative. A refundable bottle deposit may apply on first order.',
    custom: 'Custom',
    unitTjs: 'TJS',
    singleName: 'Single Bottle',
    singlePer: '/ 19L bottle',
    singleF1: 'Pay-as-you-go',
    singleF2: 'No subscription',
    singleF3: 'Delivered same / next day',
    singleCta: 'Order one',
    homeName: 'Home Pack',
    homePer: '/ 4 bottles',
    homeF1: 'Best for families',
    homeF2: 'Free delivery',
    homeF3: 'Flexible schedule',
    homeCta: 'Order Home Pack',
    officeName: 'Office Plan',
    officePer: '8+ bottles per delivery',
    officeF1: 'Discount on every bottle',
    officeF2: 'Automatic recurring orders',
    officeF3: 'Invoiced billing',
    officeCta: 'Request a quote',
  },
  coverage: {
    eyebrow: 'Coverage',
    title: 'We deliver across Dushanbe.',
    body:
      'Our couriers cover the city centre and the surrounding districts year-round. Address outside the zone? Give us a call — we’ll often arrange it.',
    whereIsMyWater: 'Where is my water?',
    mapAria: 'fann live courier coverage map',
    loading: 'Loading map…',
    personal: 'Your water is on the way',
    couriersSingular: '{count} courier on the road',
    couriersPlural: '{count} couriers on the road',
    none: 'No couriers on the road right now',
  },
  contact: {
    title: 'Order your first bottle today.',
    body: 'Tell us how many 19L bottles you need and where to bring them. We’ll handle the rest.',
    call: 'Call us',
    whatsapp: 'Chat on WhatsApp',
    phoneLabel: 'Phone',
    emailLabel: 'Email',
    serviceLabel: 'Service area',
    serviceValue: 'Dushanbe & surrounding districts',
    hoursLabel: 'Hours',
    hoursValue: 'Daily, 09:00 – 17:00',
  },
  footer: {
    rights: 'All rights reserved.',
    about: 'About',
    pricing: 'Pricing',
    contact: 'Contact',
  },
};

const ru: Dictionary = {
  nav: {
    about: 'О нас',
    whyUs: 'Почему мы',
    howItWorks: 'Как это работает',
    pricing: 'Цены',
    delivery: 'Доставка',
    contact: 'Контакты',
    dashboard: 'Кабинет',
    login: 'Войти',
    signup: 'Регистрация',
    order: 'Заказать',
    orderShort: 'Заказ',
  },
  hero: {
    badge: 'fann · из Варзобского ущелья, Таджикистан',
    headline1: 'Горная родниковая вода,',
    headline2: 'с доставкой к вашей двери.',
    subtitle: '19-литровые бутылки натуральной родниковой воды из долины Варжоб — профессионально очищенной и доставляемой на дом и в офис.',
    callToOrder: 'Заказать по телефону',
    whatsapp: 'WhatsApp',
    statBottleLabel: 'Объём бутыли',
    statBottleValue: '19 л',
    statDeliveryLabel: 'Доставка',
    statDeliveryValue: 'Круглый год',
    statSourceLabel: 'Источник',
    statSourceValue: 'Варзоб',
  },
  about: {
    eyebrow: 'Наш источник',
    title: 'Из Варзобской долины.',
    body:
      'Наша вода собирается в Варзобском ущелье, где Гиссарские горы питают глубокие природные родники талыми снегами, годами фильтровавшимися сквозь камень. Мы разливаем её рядом с источником, чтобы сохранить минеральный состав и свежий вкус.',
    bullet1: 'От природы холодная, от природы чистая — без химической обработки.',
    bullet2: 'Розлив в герметичном пищевом цехе под строгим санитарным контролем.',
    bullet3: 'Доставка в многоразовых, продезинфицированных 19-литровых бутылях.',
    locationLabel: 'Варзобское ущелье, Таджикистан',
    locationFacts: 'Высота источника: ~1 600 м. Средняя температура воды: 6–8 °C.',
  },
  features: {
    eyebrow: 'Почему мы',
    title: 'Доступная чистая вода.',
    body:
      'Мы делаем одно дело: привозим настоящую горную родниковую воду к вам домой или в офис — надёжно и по честной цене.',
    sourceTitle: 'Горный родниковый источник',
    sourceDesc:
      'Розлив прямо у источника в Варзобской долине, где альпийская талая вода Таджикистана питает чистые подземные родники.',
    filtrationTitle: 'Многоступенчатая фильтрация',
    filtrationDesc:
      'Механическая, угольная и ультрафиолетовая фильтрация сохраняют природные минералы и удаляют любые примеси.',
    deliveryTitle: 'Бесплатная доставка домой и в офис',
    deliveryDesc:
      'Круглогодичная доставка по Душанбе и пригородам. Один график — и мы всегда пополняем ваш запас.',
    mineralsTitle: 'Естественный минеральный баланс',
    mineralsDesc:
      'Лёгкий, мягкий вкус с кальцием, магнием и калием, которые действительно нужны организму.',
  },
  how: {
    eyebrow: 'Как это работает',
    title: 'Три шага.',
    step1Title: 'Сделайте заказ',
    step1Text: 'Позвоните, напишите в WhatsApp или отправьте короткое сообщение — укажите количество бутылей и адрес.',
    step2Title: 'Мы доставим',
    step2Text: 'Доставка в день заказа или на следующий день. Без минимального заказа.',
    step3Title: 'Пейте на здоровье',
    step3Text: 'Пустые бутыли заберём при следующем визите. Платите только за воду.',
  },
  pricing: {
    eyebrow: 'Цены',
    title: 'Простые и прозрачные цены.',
    body:
      'Платите за бутыль или подключите регулярную доставку — как удобнее вам или вашему офису.',
    popular: 'Популярное',
    disclaimer: 'Цены ориентировочные. При первом заказе может быть возвратный залог за бутыль.',
    custom: 'По запросу',
    unitTjs: 'TJS',
    singleName: 'Одна бутыль',
    singlePer: '/ бутыль 19 л',
    singleF1: 'Оплата по факту',
    singleF2: 'Без подписки',
    singleF3: 'Доставка в день/на следующий день',
    singleCta: 'Заказать одну',
    homeName: 'Семейный пакет',
    homePer: '/ 4 бутыли',
    homeF1: 'Лучшее для семьи',
    homeF2: 'Бесплатная доставка',
    homeF3: 'Гибкое расписание',
    homeCta: 'Заказать пакет',
    officeName: 'Для офиса',
    officePer: 'от 8 бутылей за доставку',
    officeF1: 'Скидка на каждую бутылку',
    officeF2: 'Автоматические повторяющиеся заказы',
    officeF3: 'Оплата по счёту',
    officeCta: 'Заказать пакет',
  },
  coverage: {
    eyebrow: 'Зона доставки',
    title: 'Доставляем по всему Душанбе.',
    body:
      'Наши курьеры покрывают центр города и пригороды круглый год.',
    whereIsMyWater: 'Где моя вода?',
    mapAria: 'Карта курьеров fann в реальном времени',
    loading: 'Загрузка карты…',
    personal: 'Ваша вода уже в пути',
    couriersSingular: 'В пути {count} курьер',
    couriersPlural: 'В пути {count} курьеров',
    none: 'Сейчас курьеров на маршруте нет',
  },
  contact: {
    title: 'Закажите первую бутыль уже сегодня.',
    body: 'Сообщите, сколько 19-литровых бутылей вам нужно и куда привезти. Остальное мы возьмём на себя.',
    call: 'Позвонить',
    whatsapp: 'Написать в WhatsApp',
    phoneLabel: 'Телефон',
    emailLabel: 'Эл. почта',
    serviceLabel: 'Зона обслуживания',
    serviceValue: 'Душанбе и пригороды',
    hoursLabel: 'Часы работы',
    hoursValue: 'Ежедневно, 09:00 – 17:00',
  },
  footer: {
    rights: 'Все права защищены.',
    about: 'О нас',
    pricing: 'Цены',
    contact: 'Контакты',
  },
};

const tg: Dictionary = {
  nav: {
    about: 'Дар бораи мо',
    whyUs: 'Чаро мо',
    howItWorks: 'Чӣ тавр кор мекунад',
    pricing: 'Нархҳо',
    delivery: 'Расонидан',
    contact: 'Тамос',
    dashboard: 'Утоқ',
    login: 'Ворид',
    signup: 'Бақайдгирӣ',
    order: 'Фармоиш диҳед',
    orderShort: 'Фармоиш',
  },
  hero: {
    badge: 'fann · аз водии Варзоб, Тоҷикистон',
    headline1: 'Оби тозаи кӯҳии чашмагӣ,',
    headline2: 'ба дари хонаатон.',
    subtitle:
      'Зарфҳои 19-литраи оби табиии чашмагии водии Варзоб — бо филтрсозии касбӣ ба хонаҳо ва дафтарҳои саросари Тоҷикистон ҳар ҳафта, дар тамоми сол расонида мешаванд.',
    callToOrder: 'Барои фармоиш занг занед',
    whatsapp: 'WhatsApp',
    statBottleLabel: 'Ҳаҷми зарф',
    statBottleValue: '19 л',
    statDeliveryLabel: 'Расонидан',
    statDeliveryValue: 'Тамоми сол',
    statSourceLabel: 'Сарчашма',
    statSourceValue: 'Варзоб',
  },
  about: {
    eyebrow: 'Сарчашмаи мо',
    title: 'Аз водии Варзоб — поку даст нахӯрда.',
    body:
      'Оби мо дар дараи Варзоб ҷамъоварӣ мешавад, ки кӯҳҳои Ҳисор бо обҳои барфии солҳо аз санг филтршуда чашмаҳои чуқури табииро тағзия мекунанд. Мо онро дар наздикии сарчашма мерезем, то таркиби минералӣ ва таъми тоза нигоҳ дошта шавад.',
    bullet1: 'Аз табиат хунук, аз табиат тоза — бе коркарди химиявӣ.',
    bullet2: 'Дар коргоҳи озуқавории муҳрнок ва бо назорати қатъии санитарӣ рехта мешавад.',
    bullet3: 'Дар зарфҳои бозкоркунанда ва безараргардидаи 19-литра расонида мешавад.',
    locationLabel: 'Дараи Варзоб, Тоҷикистон',
    locationFacts: 'Баландии сарчашма: ~1 600 м. Ҳарорати миёнаи об: 6–8 °C.',
  },
  features: {
    eyebrow: 'Чаро мо',
    title: 'Оби тоза набояд мушкил бошад.',
    body:
      'Мо як кор мекунем: оби ҳақиқии кӯҳии чашмагиро ба хонаи шумо ё дафтаратон бо боварӣ ва бо нархи одилона мерасонем.',
    sourceTitle: 'Сарчашмаи кӯҳии чашмагӣ',
    sourceDesc:
      'Дар сарчашмаи худи водии Варзоб рехта мешавад, ки барфҳои обшудаи Тоҷикистон чашмаҳои поки зеризаминиро тағзия мекунанд.',
    filtrationTitle: 'Филтрсозии бисёрсатҳа',
    filtrationDesc:
      'Филтрҳои механикӣ, ангиштӣ ва ултрабунафш минералҳои табииро нигоҳ медоранд ва ҳама гуна омехтаро тоза мекунанд.',
    deliveryTitle: 'Расонидани ройгон ба хона ва дафтар',
    deliveryDesc:
      'Расонидан ба Душанбе ва ноҳияҳои атроф дар тамоми сол. Як бор ҷадвал тартиб диҳед — мо захираатонро доим нигоҳ медорем.',
    mineralsTitle: 'Минералҳои табиатан мутавозин',
    mineralsDesc:
      'Таъми сабук ва мулоим бо калсий, магний ва калий — он чизе ки ҷисми шумо воқеан ниёз дорад.',
  },
  how: {
    eyebrow: 'Чӣ тавр кор мекунад',
    title: 'Се қадам. Бе ҳуҷҷатбозӣ.',
    step1Title: 'Фармоиш диҳед',
    step1Text: 'Занг занед, дар WhatsApp ё бо паёми кӯтоҳ — миқдор ва суроғаро гӯед.',
    step2Title: 'Мо мерасонем',
    step2Text: 'Расонидан дар ҳамон рӯз ё рӯзи дигар, бе ҳадди ақалли фармоиш.',
    step3Title: 'Бо тани сиҳат',
    step3Text: 'Зарфҳои холиро ҳангоми боздиди навбатӣ мегирем. Шумо танҳо барои об пардохт мекунед.',
  },
  pricing: {
    eyebrow: 'Нархҳо',
    title: 'Нархҳои оддӣ ва шаффоф.',
    body:
      'Барои ҳар зарф пардохт кунед ё расонидани доимиро ба роҳ монед — ҳамон тавре ки барои хона ё дафтаратон қулай аст.',
    popular: 'Маъмул',
    disclaimer: 'Нархҳо тахминӣ. Дар фармоиши аввал гарави баргардонидашавандаи зарф эҳтимол дорад.',
    custom: 'Бо талабот',
    unitTjs: 'TJS',
    singleName: 'Як зарф',
    singlePer: '/ зарфи 19 л',
    singleF1: 'Пардохти ҳамзамон',
    singleF2: 'Бе обуна',
    singleF3: 'Расонидани ҳамонрӯза/фардо',
    singleCta: 'Як зарф фармоед',
    homeName: 'Бастаи хонагӣ',
    homePer: '/ 4 зарф',
    homeF1: 'Беҳтарин барои оила',
    homeF2: 'Расонидани ройгон',
    homeF3: 'Ҷадвали чандир',
    homeCta: 'Бастаро фармоед',
    officeName: 'Нақшаи дафтарӣ',
    officePer: 'аз 8 зарф ҳар расонидан',
    officeF1: 'Менеҷери шахсӣ',
    officeF2: 'Кулерҳо дастрасанд',
    officeF3: 'Пардохт бо ҳисобнома',
    officeCta: 'Дархости нарх',
  },
  coverage: {
    eyebrow: 'Минтақаи расонидан',
    title: 'Мо ба тамоми Душанбе мерасонем.',
    body:
      'Қурйерҳои мо марказ ва ноҳияҳои атрофро тамоми сол фаро мегиранд. Суроғаатон берун аз минтақа аст? Занг занед — аксаран чора меҷӯем.',
    whereIsMyWater: 'Оби ман куҷост?',
    mapAria: 'Харитаи фаврии қурйерҳои fann',
    loading: 'Бор шудани харита…',
    personal: 'Оби шумо дар роҳ аст',
    couriersSingular: '{count} қурйер дар роҳ',
    couriersPlural: '{count} қурйер дар роҳ',
    none: 'Ҳоло дар роҳ ҳеҷ қурйер нест',
  },
  contact: {
    title: 'Имрӯз зарфи аввалатонро фармоед.',
    body: 'Бигӯед, чанд зарфи 19-литра ва ба кадом суроға лозим аст. Боқиашро мо ба ӯҳда мегирем.',
    call: 'Занг занед',
    whatsapp: 'Дар WhatsApp нависед',
    phoneLabel: 'Телефон',
    emailLabel: 'Почтаи электронӣ',
    serviceLabel: 'Минтақаи хидмат',
    serviceValue: 'Душанбе ва ноҳияҳои атроф',
    hoursLabel: 'Соатҳои корӣ',
    hoursValue: 'Ҳаррӯза, 11:00 – 03:00',
  },
  footer: {
    rights: 'Ҳама ҳуқуқҳо ҳифз шудаанд.',
    about: 'Дар бораи мо',
    pricing: 'Нархҳо',
    contact: 'Тамос',
  },
};

const translations: Record<Locale, Dictionary> = { en, ru, tg };
