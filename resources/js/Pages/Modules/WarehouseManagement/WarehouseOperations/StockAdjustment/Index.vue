<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, onMounted } from "vue";
import axios from "axios";
import { useToast } from "vue-toastification";
import { router } from "@inertiajs/vue3";
import ManuallyAdjustStockModal from "./ManuallyAdjustStockModal.vue";

const adjustments = ref([]);
const loading = ref(false);
const toast = useToast();

const fetchAdjustments = async () => {
    loading.value = true;
    try {
        const response = await axios.get(
            "/api/warehouse-management/stock-adjustments"
        );
        adjustments.value = response.data.data;
    } catch (error) {
        toast.error("Failed to fetch stock adjustments");
    } finally {
        loading.value = false;
    }
};

const goToCreateAdjustment = () => {
    router.visit("/warehouse-operation/stock-adjustment/create");
};

onMounted(() => {
    fetchAdjustments();
});
const showAdjustModal = ref(false);

const openManualAdjust = () => {
    showAdjustModal.value = true;
};

const closeManualAdjust = () => {
    showAdjustModal.value = false;
};

const handleSubmitted = () => {
    // Refresh adjustment list after successful submit
    fetchAdjustments();
};
</script>

<template>
    <AppLayout title="Stock Adjustments">
        <div class="p-4 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-xl font-semibold">Stock Adjustments</h1>
                <button
                    @click="openManualAdjust"
                    class="bg-indigo-600 text-white text-sm px-4 py-2 rounded hover:bg-indigo-700"
                >
                    + Manually Adjust Stock
                </button>
            </div>

            <div v-if="loading" class="text-center text-gray-500">
                Loading adjustments...
            </div>

            <div v-else-if="adjustments.length" class="space-y-4">
                <div
                    v-for="adj in adjustments"
                    :key="adj.id"
                    class="bg-white shadow rounded-lg p-4 flex justify-between items-center"
                >
                    <div>
                        <div class="font-semibold">
                            {{
                                adj.warehouse_product.supplier_product_detail
                                    .product.name
                            }}
                        </div>
                        <div class="text-sm text-gray-500">
                            SKU: {{ adj.warehouse_product.sku }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Reason: {{ adj.reason }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ adj.adjusted_at }} •
                            {{ adj.adjusted_by_user.name }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end space-y-2">
                        <span
                            :class="[
                                'text-xs font-semibold px-2 py-1 rounded-full',
                                adj.reason === 'add-stock' ||
                                adj.reason === 'found'
                                    ? 'bg-green-100 text-green-600'
                                    : 'bg-red-100 text-red-600',
                            ]"
                        >
                            {{
                                adj.reason === "add-stock" ||
                                adj.reason === "found"
                                    ? `+${adj.adjustment}`
                                    : `-${adj.adjustment}`
                            }}
                        </span>
                        <span
                            class="text-xs px-2 py-1 bg-green-100 text-green-600 rounded-full"
                        >
                            Completed
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center text-gray-500 mt-10">
                No stock adjustments found.
            </div>
        </div>
        <ManuallyAdjustStockModal
            :show="showAdjustModal"
            @close="closeManualAdjust"
            @submitted="handleSubmitted"
        />
    </AppLayout>
</template>
