<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import HeaderActions from "@/Components/HeaderActions.vue";
import FormSetup from "@/Components/Form/Setup.vue";
import FormField from "@/Components/Form/Field.vue";
import InputError from "@/Components/InputError.vue";
import { router, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import { useToast } from "vue-toastification";
import { singularizeAndFormat } from "@/utils/global";
import { useColors } from "@/Composables/useColors";

const page = usePage();
const modelName = "users";
const isSubmitting = ref(false);
const toast = useToast();

const { buttonPrimaryBgColor, buttonPrimaryTextColor } = useColors();

const roles = computed(() =>
    Array.isArray(page.props.roles) ? page.props.roles : []
);
const warehouses = computed(() =>
    Array.isArray(page.props.warehouses) ? page.props.warehouses : []
);





const companies = computed(() =>
    Array.isArray(page.props.companies) ? page.props.companies : []
);

const headerActions = ref([
    {
        text: "Go Back",
        url: `/${modelName}`,
        inertia: true,
        class: "border border-gray-400 hover:bg-gray-100 px-4 py-2 rounded text-gray-600",
    },
]);

const fields = computed(() => [
    {
        id: "company_id",
        label: "Company",
        model: "company_id",
        type: "select",
        required: true,
        options: companies.value.map((company) => ({
            value: company.id,
            text: company.name,
        })),
    },
    {
        id: "name",
        label: "Name",
        model: "name",
        type: "text",
        placeholder: "Enter full name",
        required: true,
    },
    {
        id: "email",
        label: "Email Address",
        model: "email",
        type: "email",
        placeholder: "Enter email address",
        required: true,
    },
    {
        id: "role",
        label: "Assign Role",
        model: "role",
        type: "select",
        required: true,
        options: roles.value.map((role) => ({
            value: role.name,
            text: role.name.charAt(0).toUpperCase() + role.name.slice(1),
        })),
    },
    {
        id: "password",
        label: "Password",
        model: "password",
        type: "password",
        placeholder: "Enter password",
        required: true,
    },
    //    {
    //   id: "warehouse_ids",
    //   label: "Designated Warehouses",
    //   model: "warehouse_ids",
    //   type: "multiselect",
    //   required: false,
    //   options: warehouses.value.map((wh) => ({
    //     value: wh.id,
    //     text: wh.name,
    //   })),
    //   class:
    //     "min-h-[100px] mt-1 block w-full rounded-md shadow-sm border-gray-300 focus:ring-[var(--primary-color)] focus:border-[var(--primary-color)] sm:text-sm",
    // },
]);

// const formData = ref({});
const formData = ref({
    warehouse_ids: [],
    company_id: null,
});

const errors = ref({});

const submitForm = async () => {
    try {
        isSubmitting.value = true;

        const response = await axios.post(`/api/${modelName}`, formData.value);
        toast.success("Submitted successfully!");
        const modelDataId = response.data.modelData.id;
        router.get(`/${modelName}/${modelDataId}`);
    } catch (error) {
        toast.error("Something went wrong!");
        if (error.response && error.response.data.errors) {
            errors.value = error.response.data.errors;
        }
    } finally {
        isSubmitting.value = false;
    }
};
// 🔄 Reset warehouse_ids when company changes
watch(() => formData.value.company_id, (newVal) => {
    console.log("Selected company_id:", newVal);
    formData.value.warehouse_ids = [];
});


const filteredWarehouses = computed(() => {
    if (!formData.value.company_id) return [];
    return warehouses.value.filter(
        (wh) => Number(wh.company_id) === Number(formData.value.company_id)
    );
});

</script>

<template>
    <AppLayout :title="`Create ${singularizeAndFormat(modelName)} Details`">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Create {{ singularizeAndFormat(modelName) }} Details
                </h2>
                <HeaderActions :actions="headerActions" />
            </div>
        </template>

        <div class="max-w-3xl">
            <FormSetup
                :form-classes="'md:grid md:grid-cols-1 md:gap-2'"
                :col-span="'md:col-span-1'"
                @submitted="submitForm"
            >
                <template #title>
                    Create a new
                    {{ singularizeAndFormat(modelName).toLowerCase() }}
                </template>
                <template #description>
                    <p>
                        Fill in the form below to create a new
                        {{ singularizeAndFormat(modelName).toLowerCase() }}.
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        <span class="text-red-500 font-semibold">*</span>
                        Fields with this mark are required.
                    </p>
                </template>

                <!-- Dynamic Form Fields -->
                <template #form>
                    <div v-for="field in fields" :key="field.id">
                        <FormField
                            :id="field.id"
                            :label="field.label"
                            :type="field.type"
                            :placeholder="field.placeholder"
                            :required="field.required"
                            :input-classes="field.class"
                            :options="field.options || []"
                            v-model="formData[field.model]"
                        />
                        <InputError :message="errors[field.model]" />
                    </div>

                    <!-- ✅ Filtered Warehouses -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Designated Warehouses (optional)
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div
                                v-for="warehouse in filteredWarehouses"
                                :key="warehouse.id"
                                class="flex items-center space-x-2"
                            >
                                <input
                                    type="checkbox"
                                    :id="'warehouse-' + warehouse.id"
                                    :value="warehouse.id"
                                    v-model="formData.warehouse_ids"
                                    class="text-indigo-600 border-gray-300 rounded shadow-sm focus:ring-[var(--primary-color)]"
                                />
                                <label
                                    :for="'warehouse-' + warehouse.id"
                                    class="text-sm text-gray-700"
                                >
                                    {{ warehouse.name }}
                                </label>
                            </div>
                        </div>

                        <InputError :message="errors['warehouse_ids']" />
                    </div>
                </template>

                <template #actions>
                    <button
                        type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest focus:outline-none focus:ring disabled:opacity-25 transition"
                        :class="{
                            'bg-[var(--primary-color)] hover:bg-opacity-90 active:bg-opacity-80 focus:ring-[var(--primary-color)]': true,
                        }"
                        :style="{
                            '--primary-color': buttonPrimaryBgColor,
                            color: buttonPrimaryTextColor,
                        }"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="animate-spin mr-2">
                            <svg
                                class="w-4 h-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v8z"
                                ></path>
                            </svg>
                        </span>
                        <span v-if="!isSubmitting">Save</span>
                        <span v-else>Submitting...</span>
                    </button>
                </template>
            </FormSetup>
        </div>
    </AppLayout>
</template>
<style scoped>
.multiselect {
    min-height: 42px;
}
</style>
