<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ filters: Array });
const page = usePage();

// Реактивная проверка активных фильтров через page.url
const hasActiveFilters = computed(() => {
    return page.url.includes('filters');
});

// Полный сброс
const resetFilters = () => {
    router.get(window.location.pathname, {}, {
        preserveState: false,
        replace: true
    });
};

const getCurrentQuery = () => {
    const url = new URL(page.url, window.location.origin);
    const urlParams = url.searchParams;
    const query = {};

    for (const [key, value] of urlParams.entries()) {
        const cleanKey = key.replace(/\[\]|\[\d*\]/g, '');

        if (cleanKey.startsWith('filters')) {
            if (cleanKey.includes('[price]')) {
                query[cleanKey] = value;
            } else {
                if (!query[cleanKey]) query[cleanKey] = [];
                if (!query[cleanKey].includes(value)) query[cleanKey].push(value);
            }
        } else {
            query[cleanKey] = value;
        }
    }
    return query;
};

const toggleFilter = (groupSlug, valueId) => {
    const query = getCurrentQuery();
    const groupKey = `filters[${groupSlug}]`;
    const val = String(valueId);

    if (!query[groupKey]) {
        query[groupKey] = [val];
    } else {
        const idx = query[groupKey].indexOf(val);
        idx > -1 ? query[groupKey].splice(idx, 1) : query[groupKey].push(val);
        if (query[groupKey].length === 0) delete query[groupKey];
    }

    delete query.page;
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
};

const handlePrice = (type, val) => {
    const query = getCurrentQuery();
    const priceKey = `filters[price][${type}]`;

    if (val && val !== '') {
        query[priceKey] = val;
    } else {
        delete query[priceKey];
    }

    delete query.page;
    router.get(window.location.pathname, query, {preserveState: true, replace: true});
};
</script>

<template>
    <aside class="w-72 flex-shrink-0">
        <!-- Контейнер фильтров в стиле карточки -->
        <div
            class="bg-white border border-slate-50 rounded-[2.5rem] p-8 sticky top-8 shadow-[0_24px_48px_-12px_rgba(0,0,0,0.02)]">

            <div class="flex items-center justify-between mb-10 pb-4 border-b border-slate-50">
                <h2 class="font-black text-slate-900 uppercase italic text-sm tracking-[0.2em]">Фильтры</h2>

                <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    class="text-[9px] font-bold text-rose-400 uppercase tracking-widest hover:text-rose-600 transition-colors underline underline-offset-4 decoration-rose-200"
                >
                    Очистить
                </button>
            </div>

            <div class="space-y-12">
                <div v-for="group in filters" :key="group.slug" class="space-y-5">
                    <!-- Заголовок группы -->
                    <div class="flex items-center gap-3">
                        <h3 class="text-[10px] font-bold text-slate-300 uppercase tracking-[0.25em]">{{
                                group.title
                            }}</h3>
                        <div class="h-[1px] flex-1 bg-slate-50"></div>
                    </div>

                    <!-- Цена (Range) -->
                    <div v-if="group.type === 'range'" class="flex gap-2">
                        <div class="relative flex-1">
                            <input
                                type="number"
                                :value="group.values.min"
                                @blur="e => handlePrice('min', e.target.value)"
                                @keyup.enter="e => e.target.blur()"
                                class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all placeholder:text-slate-300"
                                placeholder="ОТ"
                            >
                        </div>
                        <div class="relative flex-1">
                            <input
                                type="number"
                                :value="group.values.max"
                                @blur="e => handlePrice('max', e.target.value)"
                                @keyup.enter="e => e.target.blur()"
                                class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 text-[10px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 transition-all placeholder:text-slate-300"
                                placeholder="ДО"
                            >
                        </div>
                    </div>

                    <!-- Списки свойств (Checkbox) -->
                    <ul v-else class="space-y-3 max-h-72 overflow-y-auto px-1 py-1 custom-scrollbar overflow-x-hidden">
                        <li v-for="val in group.values" :key="val.id">
                            <label
                                :class="[
                'flex items-center gap-3 cursor-pointer group transition-all duration-300 py-0.5 rounded-lg',
                { 'opacity-20 pointer-events-none': !val.is_available && !val.is_active }
            ]"
                            >
                                <div class="relative flex items-center shrink-0">
                                    <input
                                        type="checkbox"
                                        :checked="val.is_active"
                                        @change="toggleFilter(group.slug, val.id)"
                                    class="peer appearance-none w-4 h-4 border-[1.5px] border-slate-200 rounded-md checked:bg-indigo-500 checked:border-indigo-500 transition-all cursor-pointer focus:ring-2 focus:ring-indigo-500/20 focus:ring-offset-0"
                                    >
                                    <svg class="absolute w-2.5 h-2.5 text-white opacity-0 peer-checked:opacity-100 left-[3px] pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>

                                <!-- Цвет (Индикатор) -->
                                <div v-if="group.slug === 'color' && val.hex_code"
                                     class="w-3 h-3 rounded-full ring-1 ring-slate-100 ring-offset-1 shrink-0"
                                     :style="{ backgroundColor: val.hex_code }"
                                ></div>

                                <span :class="[
                'text-[10px] font-bold uppercase tracking-tight transition-colors truncate',
                val.is_active ? 'text-indigo-600' : 'text-slate-500 group-hover:text-slate-800'
            ]">
                {{ val.title }}
            </span>

                                <span class="ml-auto text-[9px] font-mono text-slate-200 font-bold group-hover:text-slate-400 transition-colors">
                {{ val.count }}
            </span>
                            </label>
                        </li>
                    </ul>

                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #f1f5f9;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #e2e8f0;
}
</style>
