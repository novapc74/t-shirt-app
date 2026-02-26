<template>
    <div class="flex flex-col border p-5 rounded-2xl shadow-sm hover:shadow-xl transition-all bg-white group h-full">
        <div class="mb-4">
            <h4 class="font-bold text-lg text-gray-900 group-hover:text-indigo-600 transition-colors leading-tight">
                {{ product.name }}
            </h4>
            <p class="text-xl font-black text-gray-900 mt-1">
                <span v-if="!isSelected" class="text-sm font-normal text-gray-400">от</span>
                {{ currentPrice }} ₽
            </p>
        </div>

        <div class="flex-1 space-y-4 mb-2">
            <div v-for="(options, groupName) in product.grouped_specs" :key="groupName" class="flex flex-col gap-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ groupName }}</span>

                <div class="flex flex-wrap gap-1.5 items-center">
                    <button
                        v-for="opt in options"
                        :key="opt.value"
                        @click="selectOption(groupName, opt.value)"
                        :disabled="!isOptionPossible(groupName, opt.value)"
                        :class="[
                            'px-3 py-1.5 rounded-md text-[11px] font-medium transition-all border relative',
                            selection[groupName] === opt.value
                                ? 'bg-gray-50 border-indigo-600 text-indigo-700 shadow-sm'
                                : 'bg-white border-gray-100 text-gray-700 hover:border-gray-300',
                            !isOptionPossible(groupName, opt.value) ? 'opacity-25 grayscale cursor-not-allowed border-dashed' : 'opacity-100'
                        ]"
                        :style="opt.slug === 'color' ? { borderBottom: `3px solid ${opt.color_hex}` } : {}"
                    >
                        {{ opt.value }}
                    </button>

                    <!-- СКРЫВАЕМ КРЕСТИК, ЕСЛИ АКТИВЕН САЙДБАР -->
                    <button
                        v-if="selection[groupName] && !isSideFilterActive"
                        @click="resetGroup(groupName)"
                        class="p-1.5 text-gray-300 hover:text-red-500 transition-colors rounded-md"
                    >
                        <svg xmlns="http://www.w3.org" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- СКРЫВАЕМ ОБЩИЙ СБРОС, ЕСЛИ АКТИВЕН САЙДБАР -->
            <div class="h-6 flex justify-end items-center mt-3">
                <button
                    v-if="Object.keys(selection).length > 1 && !isSideFilterActive"
                    @click="resetAll"
                    class="px-2 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-600 border border-red-800/40 rounded-[4px] hover:bg-red-600 hover:text-white transition-all active:scale-95 flex items-center gap-1"
                >
                    сброс
                </button>
            </div>
        </div>

        <div class="mb-3 h-4">
            <div v-if="currentVariant" class="flex items-center gap-2">
                <span :class="['w-1.5 h-1.5 rounded-full', currentVariant.stock > 0 ? 'bg-green-500 animate-pulse' : 'bg-red-500']"></span>
                <span class="text-[9px] text-gray-500 uppercase font-bold tracking-tight">
                    {{ currentVariant.stock > 0 ? `В наличии: ${currentVariant.stock} шт.` : 'Нет на складе' }}
                </span>
            </div>
        </div>

        <button
            @click="handleAddToCart"
            :disabled="!currentVariant || currentVariant.stock <= 0"
            class="w-full bg-green-700 text-white text-center py-3.5 rounded-xl font-black uppercase text-xs tracking-widest hover:shadow-gray-400 active:scale-95 transition-all disabled:bg-gray-100 disabled:text-gray-400 shadow-lg shadow-gray-200"
        >
            {{ currentVariant ? (currentVariant.stock > 0 ? 'В корзину' : 'Нет в наличии') : 'Выберите опции' }}
        </button>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
    product: Object,
    activeFilters: Object,
    isSideFilterActive: Boolean // Добавили пропс
})

const selection = ref({})

const isOptionPossible = (groupName, value) => {
    const potential = { ...selection.value, [groupName]: value };
    return props.product.variants.some(v => {
        const matches = Object.entries(potential).every(([g, val]) => {
            return v.properties.some(p => p.group === g && (p.label === val || p.value === val));
        });
        return matches && v.stock > 0;
    });
}

const autoSelect = () => {
    let changed = false;
    Object.entries(props.product.grouped_specs).forEach(([groupName, options]) => {
        if (!selection.value[groupName]) {
            const possible = options.filter(opt => isOptionPossible(groupName, opt.value));
            if (possible.length === 1) {
                selection.value[groupName] = possible[0].value;
                changed = true;
            }
        }
    });
    if (changed) autoSelect();
}

const syncWithSidebar = () => {
    const newSelection = {};
    Object.entries(props.product.grouped_specs).forEach(([groupName, options]) => {
        const groupSlug = options[0]?.slug;
        const sidebarValues = props.activeFilters ? props.activeFilters[groupSlug] : null;
        if (sidebarValues && Array.isArray(sidebarValues) && sidebarValues.length > 0) {
            const match = options.find(o => sidebarValues.includes(o.value) && isOptionPossible(groupName, o.value));
            if (match) newSelection[groupName] = match.value;
        }
    });
    selection.value = newSelection;
    autoSelect();
}

function resetGroup(groupName) {
    if (props.isSideFilterActive) return; // Блокировка действия при активном сайдбаре
    delete selection.value[groupName];
    autoSelect();
}

function resetAll() {
    if (props.isSideFilterActive) return;
    selection.value = {};
    autoSelect();
}

function selectOption(group, value) {
    if (selection.value[group] === value) {
        resetGroup(group);
    } else {
        selection.value[group] = value;
        Object.keys(selection.value).forEach(g => {
            if (g !== group && !isOptionPossible(g, selection.value[g])) {
                delete selection.value[g];
            }
        });
    }
    autoSelect();
}

watch(() => props.activeFilters, syncWithSidebar, { deep: true });
watch(() => props.product, syncWithSidebar, { deep: true, immediate: true });
onMounted(syncWithSidebar);

const isSelected = computed(() => Object.keys(selection.value).length === Object.keys(props.product.grouped_specs).length);
const currentVariant = computed(() => {
    if (!isSelected.value) return null;
    return props.product.variants.find(v => Object.entries(selection.value).every(([g, val]) =>
        v.properties.some(p => p.group === g && (p.label === val || p.value === val))));
});
const currentPrice = computed(() => currentVariant.value ? currentVariant.value.price : props.product.min_price);
function handleAddToCart() { if (currentVariant.value) alert(`🛒 Добавлено!`); }
</script>
