<template>
    <div class="p-8 font-sans max-w-7xl mx-auto text-gray-900">
        <div class="flex gap-8">
            <aside class="w-64 flex-shrink-0">
                <h2 class="text-xl font-bold mb-4">{{ category }}</h2>

                <!-- БЛОК ЦЕНЫ -->
                <div class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Цена (₽)</h3>

                    <!-- Визуальный слайдер -->
                    <div class="relative h-1 w-full bg-gray-200 rounded-full mb-6 mt-4">
                        <div
                            class="absolute h-full bg-indigo-600 rounded-full"
                            :style="{
                                left: `${((minPrice || price_range.min) - price_range.min) / (price_range.max - price_range.min) * 100}%`,
                                right: `${100 - ((maxPrice || price_range.max) - price_range.min) / (price_range.max - price_range.min) * 100}%`
                            }"
                        ></div>
                        <input
                            type="range"
                            :min="price_range.min"
                            :max="price_range.max"
                            v-model.number="minPrice"
                            @change="updateFilters"
                            class="absolute w-full -top-1.5 h-4 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider"
                        >
                        <input
                            type="range"
                            :min="price_range.min"
                            :max="price_range.max"
                            v-model.number="maxPrice"
                            @change="updateFilters"
                            class="absolute w-full -top-1.5 h-4 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider"
                        >
                    </div>

                    <!-- Поля ввода -->
                    <div class="flex items-center gap-2 mb-4">
                        <input
                            type="number"
                            v-model.lazy="minPrice"
                            @change="updateFilters"
                            class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                            :placeholder="`от ${price_range.min}`"
                        >
                        <span class="text-gray-400 text-xs">—</span>
                        <input
                            type="number"
                            v-model.lazy="maxPrice"
                            @change="updateFilters"
                            class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                            :placeholder="`до ${price_range.max}`"
                        >
                    </div>
                </div>

                <!-- БЛОКИ СВОЙСТВ -->
                <div v-for="group in filters" :key="group.slug" class="mb-6 border-b pb-4">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">
                        {{ group.name }} <span v-if="group.unit">({{ group.unit }})</span>
                    </h3>
                    <div v-for="option in group.options" :key="option.value" class="flex items-center mb-2">
                        <label :class="[
                            'flex items-center cursor-pointer text-sm transition-all',
                            option.is_available ? 'text-gray-800 hover:text-indigo-600' : 'text-gray-300 cursor-not-allowed opacity-50'
                        ]">
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0 disabled:opacity-30"
                                :value="option.value"
                                :disabled="!option.is_available"
                                v-model="selectedFilters[group.slug]"
                                @change="updateFilters"
                            >
                            <span :class="{'line-through': !option.is_available}">{{ option.label }}</span>
                        </label>
                    </div>
                </div>

                <button @click="resetFilters" class="text-[10px] font-black uppercase tracking-tighter text-indigo-600 hover:underline">
                    Сбросить всё
                </button>
            </aside>

            <main class="flex-1">
                <div v-if="products.data.length > 0" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    <ProductCard
                        v-for="product in products.data"
                        :key="product.id"
                        :product="product"
                        :active-filters="selectedFilters"
                    />
                </div>
                <div v-else class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-400 font-medium">Товары не найдены</p>
                    <button @click="resetFilters" class="mt-2 text-indigo-600 underline text-sm">Очистить фильтры</button>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import ProductCard from '@/Components/ProductCard.vue';
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    category: String,
    filters: Array,
    products: Object,
    active_filters: Object,
    price_range: Object
})

// 1. Инициализация
const selectedFilters = ref(props.active_filters || {})

const urlParams = new URLSearchParams(window.location.search)
// Важно: приводим к числу для корректной работы слайдера
const minPrice = ref(urlParams.get('min_price') ? Number(urlParams.get('min_price')) : props.price_range.min)
const maxPrice = ref(urlParams.get('max_price') ? Number(urlParams.get('max_price')) : props.price_range.max)

const sync = () => {
    props.filters.forEach(group => {
        if (!selectedFilters.value[group.slug]) {
            selectedFilters.value[group.slug] = []
        }
    });
}

onMounted(sync);

// 2. Валидация цен (чтобы min не стал больше max)
watch([minPrice, maxPrice], ([newMin, newMax]) => {
    if (newMin > newMax) {
        minPrice.value = newMax
    }
    if (newMax < newMin) {
        maxPrice.value = newMin
    }
})

// Слежка за фильтрами (самоочистка)
watch(() => props.filters, (newFilters) => {
    let needsUpdate = false;
    newFilters.forEach(group => {
        const slug = group.slug;
        if (selectedFilters.value[slug] && selectedFilters.value[slug].length > 0) {
            const availableValues = group.options
                .filter(opt => opt.is_available)
                .map(opt => opt.value);

            const originalLength = selectedFilters.value[slug].length;
            selectedFilters.value[slug] = selectedFilters.value[slug].filter(val =>
                availableValues.includes(val)
            );

            if (selectedFilters.value[slug].length !== originalLength) {
                needsUpdate = true;
            }
        }
    });

    if (needsUpdate) {
        updateFilters();
    }
}, { deep: true });

// 3. Функции
function updateFilters() {
    router.get(window.location.pathname, {
        filters: selectedFilters.value,
        min_price: minPrice.value,
        max_price: maxPrice.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['products', 'filters', 'price_range']
    })
}

function resetFilters() {
    minPrice.value = props.price_range.min
    maxPrice.value = props.price_range.max
    selectedFilters.value = {}
    router.get(window.location.pathname)
}
</script>

<style scoped>
/* Стили для того, чтобы два ползунка накладывались друг на друга */
.range-slider::-webkit-slider-thumb {
    @apply appearance-none h-4 w-4 rounded-full bg-white border-2 border-indigo-600 pointer-events-auto shadow-sm;
}
.range-slider::-moz-range-thumb {
    @apply appearance-none h-4 w-4 rounded-full bg-white border-2 border-indigo-600 pointer-events-auto shadow-sm;
}
</style>
