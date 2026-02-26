<template>
    <div class="p-8 font-sans max-w-7xl mx-auto text-gray-900">
        <div class="flex gap-8">
            <aside class="w-64 flex-shrink-0">
                <h2 class="text-xl font-bold mb-4">{{ category }}</h2>

                <!-- 1. ТИПЫ ТОВАРОВ -->
                <div v-if="product_types && product_types.length > 0" class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Вид товара</h3>
                    <div v-for="type in product_types" :key="type.slug" class="flex items-center mb-2">
                        <label
                            class="flex items-center cursor-pointer text-sm text-gray-800 hover:text-indigo-600 transition-all">
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0"
                                :value="type.slug"
                                v-model="selectedTypes"
                                @change="updateFilters"
                            >
                            <span>{{ type.name }}</span>
                        </label>
                    </div>
                </div>

                <!-- 2. БРЕНДЫ (НОВОЕ) -->
                <div v-if="brands && brands.length > 0" class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Бренд</h3>
                    <div v-for="brand in brands" :key="brand.slug" class="flex items-center mb-2">
                        <label
                            class="flex items-center cursor-pointer text-sm text-gray-800 hover:text-indigo-600 transition-all">
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0"
                                :value="brand.slug"
                                v-model="selectedBrands"
                                @change="updateFilters"
                            >
                            <span>{{ brand.name }}</span>
                        </label>
                    </div>
                </div>

                <!-- БЛОК ЦЕНЫ -->
                <div class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Цена (₽)</h3>
                    <div class="relative h-1 w-full bg-gray-200 rounded-full mb-6 mt-4">
                        <div
                            class="absolute h-full bg-indigo-600 rounded-full"
                            :style="{
                                left: `${((minPrice || price_range.min) - price_range.min) / (price_range.max - price_range.min) * 100}%`,
                                right: `${100 - ((maxPrice || price_range.max) - price_range.min) / (price_range.max - price_range.min) * 100}%`
                            }"
                        ></div>
                        <input type="range" :min="price_range.min" :max="price_range.max" v-model.number="minPrice"
                               @change="updateFilters"
                               class="absolute w-full -top-1.5 h-4 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider">
                        <input type="range" :min="price_range.min" :max="price_range.max" v-model.number="maxPrice"
                               @change="updateFilters"
                               class="absolute w-full -top-1.5 h-4 bg-transparent appearance-none pointer-events-none cursor-pointer range-slider">
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <input type="number" v-model.lazy="minPrice" @change="updateFilters"
                               class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                               :placeholder="`от ${price_range.min}`">
                        <span class="text-gray-400 text-xs">—</span>
                        <input type="number" v-model.lazy="maxPrice" @change="updateFilters"
                               class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                               :placeholder="`до ${price_range.max}`">
                    </div>
                </div>

                <!-- БЛОКИ СВОЙСТВ -->
                <div v-for="group in filters" :key="group.slug" class="mb-6 border-b pb-4">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">
                        {{ group.name }} <span v-if="group.unit">({{ group.unit }})</span>
                    </h3>
                    <div v-for="option in group.options" :key="option.value" class="flex items-center mb-2">
                        <label
                            :class="['flex items-center cursor-pointer text-sm transition-all', option.is_available ? 'text-gray-800 hover:text-indigo-600' : 'text-gray-300 cursor-not-allowed opacity-50']">
                            <input type="checkbox"
                                   class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0 disabled:opacity-30"
                                   :value="option.value" :disabled="!option.is_available"
                                   v-model="selectedFilters[group.slug]" @change="updateFilters">
                            <span :class="{'line-through': !option.is_available}">{{ option.label }}</span>
                        </label>
                    </div>
                </div>

                <button @click="resetFilters"
                        class="text-[10px] font-black uppercase tracking-tighter text-indigo-600 hover:underline">
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
                        :is-side-filter-active="isSideFilterActive"
                    />
                </div>
                <div v-else class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-400 font-medium">Товары не найдены</p>
                    <button @click="resetFilters" class="mt-2 text-indigo-600 underline text-sm">Очистить фильтры
                    </button>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import ProductCard from '@/Components/ProductCard.vue';
import {ref, watch, onMounted, computed} from 'vue'
import {router} from '@inertiajs/vue3'

const props = defineProps({
    category: String,
    filters: Array,
    product_types: Array,
    brands: Array,        // НОВОЕ: Пропс брендов
    products: Object,
    active_filters: Object,
    active_types: Array,
    active_brands: Array, // НОВОЕ: Пропс активных брендов
    price_range: Object
})

const selectedFilters = ref(props.active_filters || {})
const selectedTypes = ref(props.active_types || [])
const selectedBrands = ref(props.active_brands || []) // НОВОЕ: Реактивность брендов

const urlParams = new URLSearchParams(window.location.search)
const minPrice = ref(urlParams.get('min_price') ? Number(urlParams.get('min_price')) : props.price_range.min)
const maxPrice = ref(urlParams.get('max_price') ? Number(urlParams.get('max_price')) : props.price_range.max)

const isSideFilterActive = computed(() => {
    const hasActiveProps = Object.values(selectedFilters.value).some(arr => Array.isArray(arr) && arr.length > 0);
    const hasActiveTypes = selectedTypes.value.length > 0;
    const hasActiveBrands = selectedBrands.value.length > 0; // НОВОЕ
    const hasPriceChanged = Math.abs(minPrice.value - props.price_range.min) > 1 || Math.abs(maxPrice.value - props.price_range.max) > 1;

    return hasActiveProps || hasActiveTypes || hasActiveBrands || hasPriceChanged;
});

const sync = () => {
    props.filters.forEach(group => {
        if (!selectedFilters.value[group.slug]) {
            selectedFilters.value[group.slug] = []
        }
    });
}

onMounted(sync);

watch([minPrice, maxPrice], ([newMin, newMax]) => {
    const gap = 100;
    if (newMax - newMin < gap) {
        if (minPrice.value > newMin) {
            maxPrice.value = newMin + gap;
        } else {
            minPrice.value = newMax - gap;
        }
    }
    // Ограничение по общим краям
    if (minPrice.value < props.price_range.min) minPrice.value = props.price_range.min;
    if (maxPrice.value > props.price_range.max) maxPrice.value = props.price_range.max;
})

function updateFilters() {
    router.get(window.location.pathname, {
        filters: selectedFilters.value,
        product_types: selectedTypes.value,
        brands: selectedBrands.value,
        min_price: minPrice.value, // Проверьте min_price vs minPrice
        max_price: maxPrice.value  // Проверьте max_price vs maxPrice
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // only: ['products', 'filters', 'product_types', 'brands', 'price_range']
    })
}


function resetFilters() {
    // 1. Немедленно сбрасываем все локальные переменные в дефолт
    minPrice.value = props.price_range.min
    maxPrice.value = props.price_range.max
    selectedTypes.value = []
    selectedBrands.value = []

    if (selectedFilters.value) {
        Object.keys(selectedFilters.value).forEach(key => {
            selectedFilters.value[key] = []
        })
    }

    // 2. Делаем переход на чистый URL без параметров поиска
    // Это самый надежный способ сбросить всё разом
    router.get(window.location.pathname, {}, {
        preserveState: false, // Обязательно false, чтобы сбросить состояние компонентов
        preserveScroll: true,
        replace: true
    })
}
</script>

<style scoped>
/* Убираем стандартные стили, чтобы сделать фон прозрачным */
.range-slider {
    -webkit-appearance: none;
    appearance: none;
    height: 2px;
    background: transparent;
    pointer-events: none; /* Пропускаем клики сквозь сам трек */
    position: absolute;
    width: 100%;
    outline: none;
}

/* Стили для самого "кружка" (thumb) — возвращаем ему кликабельность */
.range-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 16px;
    height: 16px;
    background: #4f46e5; /* indigo-600 */
    cursor: pointer;
    border-radius: 50%;
    pointer-events: auto; /* Делаем кружок активным */
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.range-slider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    background: #4f46e5;
    cursor: pointer;
    border-radius: 50%;
    pointer-events: auto;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

/* Убираем фокусные рамки */
.range-slider:focus {
    outline: none;
}
</style>

