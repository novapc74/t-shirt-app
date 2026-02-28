<script setup>
import ProductCard from '@/Components/ProductCard.vue';
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    category: String,
    filters: Array,       // Группы атрибутов с полями id, title, items (внутри items: id, title, count)
    brands: Array,        // Список брендов с id, title
    products: Object,
    active_filters: Object, // Формат { 1001: [5], 1002: [10] } (где ключи - ID свойств)
    active_brands: Array,   // Массив ID
    price_range: Object     // { min, max }
})

// Состояние фильтров (используем ID, так как индекс в БД работает по ID)
const selectedFilters = ref(props.active_filters || {})
const selectedBrands = ref(props.active_brands || [])

const minPrice = ref(props.price_range.min)
const maxPrice = ref(props.price_range.max)

const isSideFilterActive = computed(() => {
    const hasActiveProps = Object.values(selectedFilters.value).some(arr => Array.isArray(arr) && arr.length > 0);
    const hasActiveBrands = selectedBrands.value.length > 0;
    const hasPriceChanged = minPrice.value > props.price_range.min || maxPrice.value < props.price_range.max;
    return hasActiveProps || hasActiveBrands || hasPriceChanged;
})

const updateFilters = () => {
    router.get(window.location.pathname, {
        filters: selectedFilters.value, // Улетает в DTO $filters
        brands: selectedBrands.value,   // Улетает в DTO $brands
        min_price: minPrice.value,
        max_price: maxPrice.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const resetFilters = () => {
    router.get(window.location.pathname)
}

// Хелпер для чекбоксов
const toggleAttribute = (propId, valueId) => {
    if (!selectedFilters.value[propId]) {
        selectedFilters.value[propId] = [];
    }
    const index = selectedFilters.value[propId].indexOf(valueId);
    if (index > -1) {
        selectedFilters.value[propId].splice(index, 1);
    } else {
        selectedFilters.value[propId].push(valueId);
    }
    updateFilters();
}
</script>

<template>
    <div class="p-8 font-sans max-w-7xl mx-auto text-gray-900">
        <div class="flex gap-8">
            <aside class="w-64 flex-shrink-0">
                <h2 class="text-xl font-bold mb-4 uppercase tracking-tighter">{{ category }}</h2>

                <!-- БРЕНДЫ -->
                <div v-if="brands && brands.length > 0" class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Бренд</h3>
                    <div v-for="brand in brands" :key="brand.id" class="flex items-center mb-2">
                        <label class="flex items-center cursor-pointer text-sm text-gray-800 hover:text-indigo-600 transition-all">
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0"
                                :value="brand.id"
                                v-model="selectedBrands"
                                @change="updateFilters"
                            >
                            <span>{{ brand.title }}</span>
                        </label>
                    </div>
                </div>

                <!-- БЛОК ЦЕНЫ -->
                <div class="mb-6 border-b pb-6">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">Цена (₽)</h3>
                    <div class="flex items-center gap-2 mt-4">
                        <input type="number" v-model.lazy="minPrice" @change="updateFilters"
                               class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                               :placeholder="`от ${price_range.min}`">
                        <span class="text-gray-400 text-xs">—</span>
                        <input type="number" v-model.lazy="maxPrice" @change="updateFilters"
                               class="w-full text-xs border-gray-300 rounded focus:ring-indigo-600 p-1.5"
                               :placeholder="`до ${price_range.max}`">
                    </div>
                </div>

                <!-- УМНЫЕ ФИЛЬТРЫ (Цвет, Размер и т.д.) -->
                <div v-for="group in filters" :key="group.id" class="mb-6 border-b pb-4">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">
                        {{ group.title }}
                    </h3>
                    <div v-for="item in group.items" :key="item.id" class="flex items-center mb-2">
                        <label
                            :class="[
                                'flex items-center cursor-pointer text-sm transition-all',
                                item.count > 0 ? 'text-gray-800 hover:text-indigo-600' : 'text-gray-300 cursor-not-allowed opacity-40'
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0 disabled:opacity-20"
                                :disabled="item.count === 0"
                                :checked="selectedFilters[group.id]?.includes(item.id)"
                                @change="toggleAttribute(group.id, item.id)"
                            >
                            <span :class="{'line-through': item.count === 0}">{{ item.title }}</span>
                            <span class="ml-1 text-[10px] text-gray-400">({{ item.count }})</span>
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
                        :is-side-filter-active="isSideFilterActive"
                    />
                </div>
                <div v-else class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-400 font-medium font-bold uppercase tracking-widest">Товары не найдены</p>
                    <button @click="resetFilters" class="mt-2 text-indigo-600 underline text-xs font-bold uppercase">Очистить фильтры</button>
                </div>
            </main>
        </div>
    </div>
</template>
