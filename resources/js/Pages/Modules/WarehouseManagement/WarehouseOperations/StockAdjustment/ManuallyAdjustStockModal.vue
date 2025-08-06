<script setup>
import { ref, watch } from "vue";
import axios from "axios";
import { useToast } from "vue-toastification";
import { debounce } from "lodash";

const props = defineProps({
    show: Boolean,
});
const emit = defineEmits(["close", "submitted"]);

const toast = useToast();

const adjustmentType = ref("add-stock");
const productSearch = ref("");
const productOptions = ref([]);
const selectedProduct = ref(null);

const quantity = ref("");
const location = ref("");
const reason = ref("");
const remarks = ref("");

const serials = ref([
    { serial_number: "", manufactured_date: "", expiry_date: "" },
]);
const bulkManufacturedDate = ref("");
const bulkExpiryDate = ref("");

const submitting = ref(false);

const closeModal = () => {
    emit("close");
};

// Product Autocomplete
const fetchProductOptions = debounce(async (searchTerm) => {
    if (!searchTerm) {
        productOptions.value = [];
        return;
    }

    try {
        const res = await axios.get(
            "/api/warehouse-management/stock-adjustments/products/autocomplete",
            {
                params: { search: searchTerm },
            }
        );
        productOptions.value = res.data;
    } catch (error) {
        console.error("Failed to fetch products", error);
    }
}, 300);

watch(productSearch, (val) => {
    fetchProductOptions(val);
});

// Serial Row Actions
const addSerialRow = () => {
    serials.value.push({
        serial_number: "",
        manufactured_date: "",
        expiry_date: "",
    });
};

const removeSerialRow = (index) => {
    serials.value.splice(index, 1);
};

const applyBulkDates = () => {
    serials.value.forEach((serial) => {
        if (bulkManufacturedDate.value)
            serial.manufactured_date = bulkManufacturedDate.value;
        if (bulkExpiryDate.value) serial.expiry_date = bulkExpiryDate.value;
    });
};

// const generateSerialCode = () => {
//     serials.value.forEach((serial) => {
//         if (!serial.serial_number) {
//             serial.serial_number =
//                 "SN-" + Math.random().toString(36).substr(2, 9).toUpperCase();
//         }
//     });
// };
const generateSerialCodes = () => {
    const prefix = "SN"; // Since Adjustment is only for serial numbers here
    const timestamp = Date.now();

    serials.value = serials.value.map((serial, index) => {
        const code = `${prefix}-${timestamp}-${index + 1}`;
        return {
            ...serial,
            serial_number: code,
        };
    });
};
const scanInput = ref("");

// const handleSerialScan = () => {
//     const serial = scanInput.value.trim();
//     if (!serial) return;

//     // Check for duplicates
//     const exists = serials.value.some((s) => s.serial_number === serial);
//     if (exists) {
//         toast.warning("Serial number already added.");
//         scanInput.value = "";
//         return;
//     }

//     serials.value.push({ serial_number: serial });
//     scanInput.value = "";
// };

const submitAdjustment = async () => {
    if (!selectedProduct.value || !adjustmentType.value) {
        toast.error("Please fill all required fields");
        return;
    }

    // Determine adjustment quantity based on adjustment type
    const adjustmentQty =
        adjustmentType.value === "damaged" || adjustmentType.value === "found"
            ? serials.value.length
            : parseInt(quantity.value) || 0;

    if (adjustmentQty <= 0) {
        toast.error("Please add serial numbers to proceed.");
        return;
    }
    const currentQty = parseInt(selectedProduct.value.qty) || 0;

    try {
        submitting.value = true;
        await axios.post("/api/warehouse-management/stock-adjustments", {
            warehouse_id: selectedProduct.value.warehouse_id,
            warehouse_product_id: selectedProduct.value.id,
            system_quantity: currentQty,
            actual_quantity:
                adjustmentType.value === "damaged"
                    ? currentQty - adjustmentQty
                    : currentQty + adjustmentQty,
            adjustment: adjustmentQty,
            reason: adjustmentType.value,
            remarks: remarks.value,
            serials: serials.value,
        });

        toast.success("Stock adjusted successfully");
        emit("submitted");
        closeModal();
    } catch (error) {
        toast.error("Failed to adjust stock");
        console.error(error);
    } finally {
        submitting.value = false;
    }
};

watch(quantity, (val) => {
    const qty = parseInt(val) || 0;

    if (qty <= 0) {
        serials.value = [];
        return;
    }

    const currentLength = serials.value.length;

    // Add or remove rows based on quantity
    if (qty > currentLength) {
        for (let i = currentLength; i < qty; i++) {
            serials.value.push({
                serial_number: "",
                manufactured_date: "",
                expiry_date: "",
            });
        }
    } else if (qty < currentLength) {
        serials.value.splice(qty);
    }

    // Auto-generate serial codes after adjusting rows
    generateSerialCodes();
});
const validateSerial = async (serialNumber) => {
    if (!selectedProduct.value) {
        toast.error("Please select a product first");
        return null;
    }

    try {
        const res = await axios.get(
            "/api/warehouse-management/stock-adjustments/validate-serial",
            {
                params: {
                    serial_number: serialNumber,
                    warehouse_product_id: selectedProduct.value.id,
                },
            }
        );

        return res.data.valid ? res.data.data : null;
    } catch (err) {
        toast.error(err.response?.data?.message || "Serial validation failed");
        return null;
    }
};

// const handleSerialScan = async () => {
//     const serial = scanInput.value.trim();
//     if (!serial) return;

//     const exists = serials.value.some((s) => s.serial_number === serial);
//     if (exists) {
//         toast.warning("Serial number already added.");
//         scanInput.value = "";
//         return;
//     }

//     const validatedSerial = await validateSerial(serial);
//     if (validatedSerial) {
//         if (!selectedProduct.value) {
//             selectedProduct.value = validatedSerial.warehouse_product;
//             productSearch.value = validatedSerial.product_name;
//         }
//         serials.value.push({ serial_number: serial });
//         scanInput.value = "";
//     } else {
//         scanInput.value = "";
//     }
// };
const handleSerialScan = async () => {
    const serial = scanInput.value.trim();
    if (!serial) return;

    const exists = serials.value.some((s) => s.serial_number === serial);
    if (exists) {
        toast.warning("Serial number already added.");
        scanInput.value = "";
        return;
    }

    const validatedSerial = await validateSerial(serial);
    if (validatedSerial) {
        if (!selectedProduct.value) {
            selectedProduct.value = validatedSerial.warehouse_product;
            productSearch.value = validatedSerial.product_name;
        }
        serials.value.push({ serial_number: serial, status: "valid" });
        scanInput.value = "";
    } else {
        serials.value.push({ serial_number: serial, status: "invalid" });
        scanInput.value = "";
    }
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
        <div
            class="bg-white w-full max-w-4xl p-6 rounded-lg shadow-lg relative overflow-y-auto max-h-[90vh]"
        >
            <h2 class="text-lg font-semibold mb-4">Manually Adjust Stock</h2>

            <!-- Adjustment Type -->
            <div class="mb-4">
                <label class="text-sm font-medium">Adjustment Type *</label>
                <div class="space-y-2 mt-2">
                    <!-- Add Stock -->
                    <button
                        @click="adjustmentType = 'add-stock'"
                        :class="
                            adjustmentType === 'add-stock'
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-300 bg-white'
                        "
                        class="block w-full border p-3 rounded-lg text-left flex items-center space-x-3"
                    >
                        <span
                            class="mdi mdi-plus-box text-xl text-blue-500"
                        ></span>
                        <span>Add Stock</span>
                    </button>

                    <!-- Damaged -->
                    <button
                        @click="adjustmentType = 'damaged'"
                        :class="
                            adjustmentType === 'damaged'
                                ? 'border-red-500 bg-red-50'
                                : 'border-gray-300 bg-white'
                        "
                        class="block w-full border p-3 rounded-lg text-left flex items-center space-x-3"
                    >
                        <span
                            class="mdi mdi-alert-octagon text-xl text-red-500"
                        ></span>
                        <span>Mark as Damaged</span>
                    </button>

                    <!-- Lost -->
                    <button
                        @click="adjustmentType = 'lost'"
                        :class="
                            adjustmentType === 'lost'
                                ? 'border-yellow-500 bg-yellow-50'
                                : 'border-gray-300 bg-white'
                        "
                        class="block w-full border p-3 rounded-lg text-left flex items-center space-x-3"
                    >
                        <span
                            class="mdi mdi-package-variant-remove text-xl text-yellow-500"
                        ></span>
                        <span>Mark as Lost</span>
                    </button>

                    <!-- Found -->
                    <button
                        @click="adjustmentType = 'found'"
                        :class="
                            adjustmentType === 'found'
                                ? 'border-green-500 bg-green-50'
                                : 'border-gray-300 bg-white'
                        "
                        class="block w-full border p-3 rounded-lg text-left flex items-center space-x-3"
                    >
                        <span
                            class="mdi mdi-magnify-scan text-xl text-green-500"
                        ></span>
                        <span>Mark as Found</span>
                    </button>
                </div>
            </div>

            <!-- Product Autocomplete -->
            <div class="mb-4">
                <label class="text-sm font-medium">Product *</label>
                <input
                    v-model="productSearch"
                    type="text"
                    class="w-full border rounded px-3 py-2"
                    placeholder="Search product..."
                />
                <div
                    v-if="productOptions.length"
                    class="mt-2 border rounded bg-white shadow max-h-40 overflow-y-auto"
                >
                    <div
                        v-for="option in productOptions"
                        :key="option.id"
                        @click="
                            selectedProduct = option;
                            productSearch =
                                option.supplier_product_detail.product.name;
                            productOptions = [];
                        "
                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                    >
                        {{ option.supplier_product_detail.product.name }} (SKU:
                        {{ option.sku }})
                    </div>
                </div>
            </div>

            <!-- Quantity (Only show if NOT damaged/found) -->
            <div
                class="mb-4"
                v-if="
                    adjustmentType !== 'damaged' && adjustmentType !== 'found'
                "
            >
                <label class="text-sm font-medium">Quantity *</label>
                <input
                    v-model="quantity"
                    type="number"
                    class="w-full border rounded px-3 py-2"
                    placeholder="Enter quantity"
                />
            </div>

            <!-- Location -->
            <div class="mb-4">
                <label class="text-sm font-medium">Location (Optional)</label>
                <input
                    v-model="location"
                    type="text"
                    class="w-full border rounded px-3 py-2"
                    placeholder="Override location"
                />
            </div>

            <!-- Reason -->
            <div class="mb-4">
                <label class="text-sm font-medium">Reason *</label>
                <select
                    v-model="adjustmentType"
                    class="w-full border rounded px-3 py-2"
                >
                    <option value="damaged">Damaged</option>
                    <option value="lost">Lost</option>
                    <option value="count-correction">Count Correction</option>
                    <option value="other">Other</option>
                    <option value="add-stock">Add Stock</option>
                    <option value="found">Found</option>
                </select>
            </div>

            <!-- Remarks Textarea -->
            <div class="mb-4">
                <label class="text-sm font-medium">Remarks (Optional)</label>
                <textarea
                    v-model="remarks"
                    class="w-full border rounded px-3 py-2"
                    placeholder="Provide additional notes..."
                ></textarea>
            </div>

            <!-- Only show serials if Add Stock is selected -->
            <div v-if="adjustmentType === 'add-stock'">
                <!-- Bulk Dates -->
                <div class="mb-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium"
                            >Bulk Manufactured Date</label
                        >
                        <input
                            v-model="bulkManufacturedDate"
                            type="date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium"
                            >Bulk Expiry Date</label
                        >
                        <input
                            v-model="bulkExpiryDate"
                            type="date"
                            class="w-full border rounded px-3 py-2"
                        />
                    </div>
                </div>

                <div class="flex space-x-3 mb-4">
                    <button
                        @click="applyBulkDates"
                        class="flex-1 bg-gray-200 text-sm px-4 py-2 rounded"
                    >
                        Apply Bulk Dates
                    </button>
                    <button
                        @click="generateSerialCodes"
                        class="flex-1 bg-gray-200 text-sm px-4 py-2 rounded"
                    >
                        Generate Serial Code
                    </button>
                </div>

                <!-- Serials Table -->
                <table class="w-full text-sm mb-4 border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Serial Number</th>
                            <th class="p-2 text-left">Manufactured Date</th>
                            <th class="p-2 text-left">Expiry Date</th>
                            <th class="p-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(serial, index) in serials" :key="index">
                            <td class="p-2">
                                <input
                                    v-model="serial.serial_number"
                                    type="text"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>
                            <td class="p-2">
                                <input
                                    v-model="serial.manufactured_date"
                                    type="date"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>
                            <td class="p-2">
                                <input
                                    v-model="serial.expiry_date"
                                    type="date"
                                    class="w-full border rounded px-2 py-1"
                                />
                            </td>
                            <td class="p-2 text-center">
                                <button
                                    @click="removeSerialRow(index)"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    ✕
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- <div class="mb-4">
                    <button
                        @click="addSerialRow"
                        class="text-sm text-blue-600 hover:underline"
                    >
                        + Add Serial Number
                    </button>
                </div> -->
            </div>
            <!-- Serial Scanning / Manual Entry for Damaged or Found -->

            <div
                v-if="
                    adjustmentType === 'damaged' || adjustmentType === 'found'
                "
                class="mb-4"
            >
                <label class="text-sm font-medium">Serial Numbers *</label>
                <p class="text-xs text-gray-500 mb-2">
                    Scan or manually enter serial numbers
                </p>

                <div class="flex mb-2">
                    <input
                        v-model="scanInput"
                        @keyup.enter="handleSerialScan"
                        type="text"
                        class="w-full border rounded px-3 py-2"
                        placeholder="Scan barcode or type serial number then press Enter"
                    />
                    <button
                        @click="handleSerialScan"
                        class="ml-2 px-3 py-2 bg-blue-500 text-white rounded"
                    >
                        Add
                    </button>
                </div>

                <table class="w-full text-sm mb-2 border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left">Serial Number</th>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(serial, index) in serials" :key="index">
                            <td class="p-2">{{ serial.serial_number }}</td>
                            <td class="p-2">
                                <span
                                    :class="
                                        serial.status === 'valid'
                                            ? 'text-green-600'
                                            : 'text-red-600'
                                    "
                                    class="font-semibold"
                                >
                                    {{
                                        serial.status === "valid"
                                            ? "Valid"
                                            : "Invalid"
                                    }}
                                </span>
                            </td>
                            <td class="p-2 text-center">
                                <button
                                    @click="removeSerialRow(index)"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    ✕
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3">
                <button @click="closeModal" class="px-4 py-2 rounded border">
                    Cancel
                </button>
                <button
                    @click="submitAdjustment"
                    :disabled="submitting"
                    class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                >
                    {{ submitting ? "Saving..." : "Submit" }}
                </button>
            </div>
        </div>
    </div>
</template>
