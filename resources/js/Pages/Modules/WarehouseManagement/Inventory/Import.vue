<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Dropzone from "@/Components/Form/Dropzone.vue";
import InputError from "@/Components/InputError.vue";
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const file = ref(null);
const errors = ref({});
const isSubmitting = ref(false);
const toast = useToast();

const submitForm = async () => {
    if (!file.value) {
        errors.value.file = "Please select a file";
        toast.error("Please select a file to upload.");
        return;
    }

    const formData = new FormData();
    formData.append("file", file.value);

    try {
        isSubmitting.value = true;
        const res = await axios.post("/api/inventory/bulk-upload", formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        toast.success("File uploaded successfully!");
        router.get("/inventory");
    } catch (error) {
        toast.error("Import failed.");
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    } finally {
        isSubmitting.value = false;
    }
};

const downloadSampleCSV = () => {
    const csvHeaders = [
        "Product Name", "Warehouse", "Supplier", "Category", "Qty",
        "Critical Level", "Cost Price", "Selling Price"
    ];
    const sampleRows = [
        ["LED Bulb", "Main Warehouse", "Acme Co", "Electrical", "100", "5", "50", "75"],
        ["HDMI Cable", "Secondary Warehouse", "TechHub", "Electronics", "200", "10", "20", "35"]
    ];
    const csvContent =
        "data:text/csv;charset=utf-8," +
        csvHeaders.join(",") + "\n" +
        sampleRows.map(r => r.map(v => `"${v}"`).join(",")).join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "inventory-sample.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout title="Import Inventory">
        <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md mx-auto bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                    Upload Inventory CSV File
                </h2>
                <p class="text-sm mb-4">
                    <button @click="downloadSampleCSV" class="text-blue-600 underline hover:text-blue-800">
                        Download sample CSV format
                    </button>
                    <br />
                    Upload a CSV file containing inventory products. Only <code>.csv</code> format is supported.
                </p>

                <Dropzone
                    v-model="file"
                    :accepted-files="'.csv'"
                    :max-files="1"
                    :max-filesize="5"
                />

                <InputError :message="errors.file" class="mt-2 text-sm text-red-600" />

                <button
                    @click="submitForm"
                    class="mt-6 w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50"
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? 'Importing...' : 'Import' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
