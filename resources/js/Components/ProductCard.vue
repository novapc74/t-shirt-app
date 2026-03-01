<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    product: Object,
    activeFilters: {
        type: Object,
        default: () => ({})
    }
});

const SYSTEM_MAP = {
    '1001': 'color',
    '1002': 'size',
    '1003': 'gender'
};

const selection = ref({});

const groupedSpecs = computed(() => {
    const specs = {};
    if (!props.product.variants) return specs;

    props.product.variants.forEach(variant => {
        Object.entries(variant.attribute_names).forEach(([key, name]) => {
            const propId = Object.keys(SYSTEM_MAP).find(k => SYSTEM_MAP[k] === key) || key;

            if (!specs[propId]) {
                specs[propId] = {
                    title: propId === '1001' ? 'Цвет' : (propId === '1002' ? 'Размер' : 'Параметр'),
                    values: {}
                };
            }
            const valId = variant.attributes[key];
            specs[propId].values[valId] = name;
        });
    });
    return specs;
});

const isValueAvailable = (propId, valueId) => {
    const vId = Number(valueId);
    const attrKey = SYSTEM_MAP[propId] || propId;

    return props.product.variants.some(variant => {
        if (Number(variant.stock) <= 0) return false;

        const matchesValue = Number(variant.attributes[attrKey]) === vId;
        const matchesOthers = Object.entries(selection.value).every(([pId, sVal]) => {
            if (pId === propId || sVal === null) return true;
            const otherAttrKey = SYSTEM_MAP[pId] || pId;
            return Number(variant.attributes[otherAttrKey]) === Number(sVal);
        });
        return matchesValue && matchesOthers;
    });
};

const syncWithGlobalFilters = () => {
    if (!props.activeFilters) return;

    Object.keys(groupedSpecs.value).forEach(propId => {
        const globalValues = props.activeFilters[propId];
        const attrKey = SYSTEM_MAP[propId] || propId;

        if (Array.isArray(globalValues) && globalValues.length > 0) {
            const lastSelectedId = Number(globalValues[globalValues.length - 1]);
            const exists = props.product.variants.some(v => Number(v.attributes[attrKey]) === lastSelectedId);

            if (exists) {
                selection.value[propId] = lastSelectedId;
            }
        } else {
            // КОРЕНЬ ИСПРАВЛЕНИЯ: Если в сайдбаре сняли все галочки этой группы,
            // снимаем выбор и в карточке товара
            selection.value[propId] = null;
        }
    });
};

watch(() => props.activeFilters, () => {
    syncWithGlobalFilters();
}, { deep: true, immediate: true });

const currentVariant = computed(() => {
    const groups = Object.keys(groupedSpecs.value);
    const selectedKeys = Object.keys(selection.value).filter(k => selection.value[k] !== null);

    if (groups.length > 0 && selectedKeys.length === groups.length) {
        return props.product.variants.find(v =>
            groups.every(g => {
                const attrKey = SYSTEM_MAP[g] || g;
                return Number(v.attributes[attrKey]) === Number(selection.value[g]);
            })
        );
    }
    return null;
});

const selectValue = (propId, valueId) => {
    selection.value[propId] = Number(valueId);
};

const resetGroup = (propId) => {
    selection.value[propId] = null;
};

const resetAll = () => {
    Object.keys(groupedSpecs.value).forEach(key => {
        selection.value[key] = null;
    });
};

const hasAnySelection = computed(() => {
    return Object.values(selection.value).some(v => v !== null);
});
</script>

<template>
    <div class="bg-white rounded-2xl p-5 border border-gray-100 hover:shadow-2xl transition-all flex flex-col h-full relative group/card">

        <div class="aspect-square bg-gray-50 rounded-xl mb-4 overflow-hidden flex items-center justify-center relative">
            <span class="text-[10px] text-gray-300 font-black uppercase tracking-widest">Photo</span>
            <div v-if="currentVariant" class="absolute bottom-2 left-2 bg-black/50 backdrop-blur-sm text-[8px] text-white px-2 py-1 rounded-md font-bold uppercase tracking-widest">
                Арт: {{ currentVariant.sku }}
            </div>
        </div>

        <div class="flex-1">
            <h3 class="font-bold text-sm mb-1 line-clamp-2 leading-tight">{{ product.name }}</h3>
            <div class="flex justify-between items-center mb-4 h-4">
                <p class="text-[10px] text-gray-400 uppercase font-medium tracking-wider">
                    {{ product.category_name }}
                </p>
                <button
                    v-if="hasAnySelection"
                    @click="resetAll"
                    class="text-[9px] font-black uppercase text-indigo-600 hover:text-rose-500 transition-colors"
                >
                    Сбросить всё
                </button>
            </div>

            <div v-for="(group, propId) in groupedSpecs" :key="propId" class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-[9px] text-gray-400 uppercase font-bold tracking-tighter">{{ group.title }}</p>
                    <button
                        v-if="selection[propId] !== null"
                        @click="resetGroup(propId)"
                        class="text-[8px] uppercase font-bold text-gray-300 hover:text-indigo-600 transition-colors"
                    >
                        Очистить
                    </button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="(name, valId) in group.values"
                        :key="valId"
                        @click="selectValue(propId, valId)"
                        :class="[
                            'px-2.5 py-1.5 text-[10px] rounded-lg border font-bold transition-all',
                            Number(selection[propId]) === Number(valId)
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-100'
                                : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400',
                            !isValueAvailable(propId, valId)
                                ? 'opacity-20 grayscale pointer-events-none line-through'
                                : ''
                        ]"
                    >
                        {{ name }}
                    </button>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-gray-50 flex items-center justify-between">
            <div class="transition-all duration-300">
                <p class="text-[10px] text-gray-400 uppercase font-black leading-none mb-1">
                    {{ currentVariant ? 'Вариант' : 'Цена от' }}
                </p>
                <p class="text-xl font-black text-gray-900 leading-none">
                    {{ currentVariant ? currentVariant.price : product.min_price }} ₽
                </p>
            </div>

            <div class="text-right">
                <p class="text-[10px] text-gray-400 uppercase font-black leading-none mb-1">Остаток</p>
                <p :class="[
                    'text-xs font-black uppercase transition-all duration-300',
                    currentVariant?.stock > 0 ? 'text-green-500' : 'text-rose-400'
                ]">
                    {{ currentVariant
                    ? (currentVariant.stock > 0 ? currentVariant.stock + ' шт' : 'Нет')
                    : 'Выбор...'
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
