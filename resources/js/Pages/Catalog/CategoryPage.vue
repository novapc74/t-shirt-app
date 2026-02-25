<template>
    <div class="p-8 font-sans max-w-7xl mx-auto text-gray-900">
        <div class="flex gap-8">
            <aside class="w-64 flex-shrink-0">
                <h2 class="text-xl font-bold mb-4">{{ category }}</h2>
                <div v-for="group in filters" :key="group.slug" class="mb-6 border-b pb-4">
                    <h3 class="font-bold mb-3 uppercase text-[10px] text-gray-400 tracking-widest">
                        {{ group.name }} <span v-if="group.unit">({{ group.unit }})</span>
                    </h3>
                    <div v-for="option in group.options" :key="option.value" class="flex items-center mb-2">
                        <label :class="['flex items-center cursor-pointer text-sm transition-all', option.is_available ? 'text-gray-800 hover:text-indigo-600' : 'text-gray-300 pointer-events-none']">
                            <input
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-0 disabled:opacity-20"
                                :value="option.value"
                                :disabled="!option.is_available"
                                v-model="selectedFilters[group.slug]"
                                @change="updateFilters"
                            >
                            <span :class="{'line-through opacity-50': !option.is_available}">{{ option.label }}</span>
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
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    category: String,
    filters: Array,
    products: Object,
    active_filters: Object
})

const selectedFilters = ref(props.active_filters || {})

const sync = () => {
    props.filters.forEach(group => {
        if (!selectedFilters.value[group.slug]) selectedFilters.value[group.slug] = []
    })
}
sync();

watch(() => props.active_filters, (newVal) => {
    selectedFilters.value = newVal || {}
    sync()
}, { deep: true });

function updateFilters() {
    router.get(window.location.pathname, { filters: selectedFilters.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['products', 'filters']
    })
}

function resetFilters() {
    router.get(window.location.pathname)
}
</script>
