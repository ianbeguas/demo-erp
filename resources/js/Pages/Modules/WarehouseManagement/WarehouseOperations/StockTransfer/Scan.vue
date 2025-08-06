<script setup>
import { ref, computed } from "vue";
import { useToast } from "vue-toastification";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import BarcodeScanner from "./BarcodeScanner.vue";

const toast = useToast();
const props = defineProps({
    transfer: Object,
    savedProgress: {
        type: Object,
        default: () => ({
            scanned_serials: [],
            manually_completed_details: [],
        }),
    },
});

const scannedSerials = ref([...props.savedProgress.scanned_serials]);
const serialInput = ref("");
const manuallyCompletedDetails = ref([
    ...props.savedProgress.manually_completed_details,
]);

const totalExpectedQty = computed(() =>
    props.transfer.details.reduce((sum, detail) => sum + detail.expected_qty, 0)
);

const scannedQty = computed(() => {
    let serialCount = scannedSerials.value.length;
    let nonSerialCount = manuallyCompletedDetails.value.reduce(
        (sum, detailId) => {
            const detail = props.transfer.details.find(
                (d) => d.id === detailId
            );
            return sum + (detail ? detail.expected_qty : 0);
        },
        0
    );
    return serialCount + nonSerialCount;
});

const allItems = computed(() => {
    return props.transfer.details.map((detail) => {
        const originProduct = detail.origin_product;
        const destinationProduct = detail.destination_product;

        const scannedForProduct = scannedSerials.value.filter((serialNumber) =>
            originProduct.serials.some((s) => s.serial_number === serialNumber)
        ).length;

        const isCompleted = originProduct.has_serials
            ? scannedForProduct >= detail.expected_qty
            : manuallyCompletedDetails.value.includes(detail.id);

        return {
            id: detail.id,
            name:
                originProduct?.supplier_product_detail?.product?.name ??
                "Unknown",
            qty: detail.expected_qty,
            scannedQty: scannedForProduct,
            serials: originProduct.serials.map((s) => s.serial_number),
            hasSerials: originProduct.has_serials,
            isCompleted: isCompleted,
        };
    });
});


function scanSerial() {
    const value = serialInput.value.trim();

    const productItem = allItems.value.find((item) =>
        item.serials.includes(value)
    );

    if (!productItem) {
        toast.error("Serial not found in transfer.");
        return;
    }

    if (scannedSerials.value.includes(value)) {
        toast.warning("This serial is already scanned.");
        return;
    }

    if (productItem.scannedQty >= productItem.qty) {
        toast.warning(
            "All required quantity for this product has been scanned."
        );
        return;
    }

    scannedSerials.value.push(value);
    serialInput.value = "";
}

function markNonSerialAsCompleted(detailId) {
    if (!manuallyCompletedDetails.value.includes(detailId)) {
        manuallyCompletedDetails.value.push(detailId);
    }
}

const isTransferReady = computed(() =>
    allItems.value.every((item) => item.isCompleted)
);

async function completeTransfer() {
    try {
        await axios.post(
            `/api/warehouse-stock-transfer/${props.transfer.id}/complete`,
            {
                scanned_serials: scannedSerials.value,
                manually_completed_details: manuallyCompletedDetails.value,
            }
        );

        toast.success("Transfer marked as completed!");
        router.visit("/warehouse-stock-transfers");
    } catch (error) {
        console.error(error);

        if (error.response && error.response.status === 422) {
            toast.error(error.response.data.message);
        } else {
            toast.error("Failed to complete transfer.");
        }
    }
}

async function saveProgress() {
    try {
        await axios.post(
            `/api/warehouse-stock-transfer/${props.transfer.id}/save-progress`,
            {
                scanned_serials: scannedSerials.value,
                manually_completed_details: manuallyCompletedDetails.value,
            }
        );
        toast.success("Progress saved successfully!");
    } catch (error) {
        console.error(error);
        toast.error("Failed to save progress.");
    }
}

function onCameraScanSuccess(decodedText) {
    serialInput.value = decodedText;
    scanSerial();
    toast.success(`Scanned: ${decodedText}`);
}
</script>

<template>
    <div class="p-6 space-y-4">
        <h2 class="text-xl font-semibold">
            Transfer to: {{ transfer.destination_warehouse.name }}
        </h2>
        <p class="text-gray-500">
            Scan each serial number or mark non-serial products as scanned
        </p>

        <div class="w-full bg-gray-200 rounded h-2 relative overflow-hidden">
            <div
                class="bg-green-500 h-2 absolute top-0 left-0 transition-all"
                :style="{ width: `${(scannedQty / totalExpectedQty) * 100}%` }"
            ></div>
        </div>
        <p class="text-sm text-center text-gray-600">
            {{ scannedQty }} of {{ totalExpectedQty }} items scanned
        </p>

        <!-- <div
            v-for="item in allItems"
            :key="item.id"
            :class="item.isCompleted ? 'opacity-50 pointer-events-none' : ''"
            class="p-4 border rounded mb-2"
        >
            <div class="font-bold">{{ item.name }}</div>
            <div>Qty to Transfer: {{ item.qty }}</div>
            <div class="text-sm text-gray-500">
                Scanned: {{ item.scannedQty }}
            </div>
            <div>
                <span
                    class="inline-block px-2 py-1 text-xs font-bold rounded"
                    :class="
                        item.isCompleted
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-700'
                    "
                >
                    {{ item.isCompleted ? "Completed" : "Pending" }}
                </span>
            </div>
        </div> -->

        <div
            v-for="item in allItems"
            :key="item.id"
            :class="item.isCompleted ? 'opacity-50 pointer-events-none' : ''"
            class="p-4 border rounded mb-2"
        >
            <div class="font-bold">{{ item.name }}</div>
            <div>Qty to Transfer: {{ item.qty }}</div>
            <div class="text-sm text-gray-500">
                Scanned: {{ item.scannedQty }} {{ !item.hasSerials ? "+" : "" }}
                {{
                    item.hasSerials
                        ? ""
                        : manuallyCompletedDetails.includes(item.id)
                        ? item.qty
                        : 0
                }}
            </div>
            <div>
                <span
                    class="inline-block px-2 py-1 text-xs font-bold rounded"
                    :class="
                        item.isCompleted
                            ? 'bg-green-100 text-green-700'
                            : 'bg-yellow-100 text-yellow-700'
                    "
                >
                    {{ item.isCompleted ? "Completed" : "Pending" }}
                </span>
            </div>

            <!-- 🔥 SHOW BUTTON for Non-Serial Products that are not Completed -->
            <div v-if="!item.hasSerials && !item.isCompleted" class="mt-2">
                <button
                    @click="markNonSerialAsCompleted(item.id)"
                    class="bg-blue-500 text-white px-3 py-1 rounded text-xs"
                >
                    Mark as Scanned
                </button>
            </div>
        </div>

        <input
            v-model="serialInput"
            @keyup.enter="scanSerial"
            type="text"
            placeholder="Scan serial number..."
            class="w-full px-4 py-2 border rounded"
        />
        <BarcodeScanner :onScanSuccess="onCameraScanSuccess" />

        <button
            class="w-full bg-yellow-500 text-white py-2 rounded mt-2 flex justify-center items-center space-x-2"
            @click="saveProgress"
        >
            <span>💾</span>
            <span>Save Progress</span>
        </button>

        <button
            class="w-full bg-gray-600 text-white py-2 rounded mt-2 flex justify-center items-center space-x-2"
            @click="scanSerial"
        >
            <span>🔍</span>
            <span>Scan Serial Number</span>
        </button>

        <button
            :disabled="!isTransferReady"
            class="w-full bg-indigo-600 text-white py-2 rounded mt-4 disabled:opacity-50"
            @click="completeTransfer"
        >
            ✅ Complete Transfer
        </button>
    </div>
</template>

<style scoped>
input:focus {
    outline: none;
    border-color: #4f46e5;
}
button:disabled {
    cursor: not-allowed;
}
</style>
