<script setup>
import ProductCard from '@/Components/ProductCard.vue';
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    category: String,
    filters: Array,
    brands: Array,
    products: Object,
    active_filters: [Object, Array],
    active_brands: Array,
    price_range: Object,
    current_price: Object
})

// Инициализация состояния из пропсов
const state = reactive({
    filters: Array.isArray(props.active_filters) ? {} : JSON.parse(JSON.stringify(props.active_filters)),
    brands: [...props.active_brands],
    minPrice: props.current_price?.min || props.price_range.min,
    maxPrice: props.current_price?.max || props.price_range.max
})

// Синхронизация состояния при обновлении пропсов (ответ от сервера)
watch(() => props.active_filters, (newVal) => {
    Object.keys(state.filters).forEach(key => delete state.filters[key]);
    if (!Array.isArray(newVal)) {
        Object.assign(state.filters, JSON.parse(JSON.stringify(newVal)));
    }
}, { deep: true });

watch(() => props.active_brands, (newVal) => {
    state.brands = [...newVal];
}, { deep: true });

const updateFilters = () => {
    router.get(window.location.pathname, {
        filters: state.filters,
        brands: state.brands,
        min_price: state.minPrice,
        max_price: state.maxPrice,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const resetFilters = () => {
    // Сбрасываем локальное состояние
    Object.keys(state.filters).forEach(key => delete state.filters[key]);
    state.brands = [];
    state.minPrice = props.price_range.min;
    state.maxPrice = props.price_range.max;

    // Делаем чистый запрос без параметров
    router.get(window.location.pathname)
}

const toggleAttribute = (propId, valueId) => {
    const pId = String(propId);
    const vId = Number(valueId); // Приводим к числу

    if (!state.filters[pId] || !Array.isArray(state.filters[pId])) {
        state.filters[pId] = [];
    }

    // КРИТИЧЕСКИЙ МОМЕНТ:
    // Приводим все элементы текущего массива к числам перед поиском индекса
    const currentValues = state.filters[pId].map(Number);
    const index = currentValues.indexOf(vId);

    if (index > -1) {
        // Если нашли — удаляем из оригинального массива state
        state.filters[pId].splice(index, 1);

        if (state.filters[pId].length === 0) {
            delete state.filters[pId];
        }
    } else {
        // Если не нашли — добавляем число
        state.filters[pId].push(vId);
    }

    updateFilters();
}
const isChecked = (propId, valueId) => {
    const pId = String(propId);
    const vId = Number(valueId);

    if (!state.filters[pId] || !Array.isArray(state.filters[pId])) {
        return false;
    }

    return state.filters[pId].map(Number).includes(vId);
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
                                v-model="state.brands"
                                @change="updateFilters"
                            >
                            <span>{{ brand.title }}</span>
                        </label>
                    </div>
                </div>

                <!-- УМНЫЕ ФИЛЬТРЫ -->
                <div v-for="group in filters" :key="group.id" class="mb-6 border-b pb-4">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">
                        {{ group.title }}
                    </h3>
                    <div v-for="item in group.items" :key="item.id" class="flex items-center mb-2">
                        <label
                            :class="[
            'flex items-center text-sm transition-all w-full',
            // Блокируем ТОЛЬКО если disabled пришел true от сервера
            (item.disabled && !isChecked(group.id, item.id))
                ? 'opacity-30 cursor-not-allowed pointer-events-none'
                : 'text-gray-800 hover:text-indigo-600 cursor-pointer'
        ]"
                        >
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0"
                                :disabled="item.disabled && !isChecked(group.id, item.id)"
                                :checked="isChecked(group.id, item.id)"
                                @change="toggleAttribute(group.id, item.id)"
                            >
                            <!-- Текст зачеркиваем тоже только по флагу disabled -->
                            <span :class="{ 'line-through opacity-50': item.disabled && !isChecked(group.id, item.id) }">
            {{ item.title }}
        </span>
                            <span class="ml-auto text-[10px] text-gray-400">
            ({{ item.count }})
        </span>
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
                        :active-filters="state.filters"
                    />
                </div>
                <div v-else class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                    <p class="text-gray-400 font-bold uppercase tracking-widest">Товары не найдены</p>
                    <button @click="resetFilters" class="mt-2 text-indigo-600 underline text-xs font-bold uppercase">Очистить фильтры</button>
                </div>
            </main>
        </div>
    </div>
</template>
