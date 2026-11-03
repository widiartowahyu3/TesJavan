<script setup>
import { onMounted, ref } from 'vue';
import { api } from './api';

const name = ref('');
const result = ref(null);
const saved = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');

const pct = (v) => (v == null ? '-' : `${(v * 100).toFixed(1)}%`);
const genderLabel = (g) => ({ male: 'Laki-laki', female: 'Perempuan' })[g] ?? 'Tidak diketahui';
const fmtDate = (d) => new Date(d).toLocaleString('id-ID');

async function search() {
    if (!name.value.trim()) return;
    loading.value = true;
    error.value = '';
    notice.value = '';
    result.value = null;
    try {
        result.value = await api.lookup(name.value.trim());
        if (result.value.source === 'database') {
            notice.value = 'Limit API harian habis — menampilkan data yang sudah tersimpan di database.';
        } else if (result.value.source === 'cache') {
            notice.value = 'Menampilkan hasil dari cache (pencarian hari ini) untuk menghemat kuota API.';
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    try {
        const row = await api.save(result.value);
        saved.value.unshift(row);
        notice.value = `Data "${row.name}" berhasil disimpan.`;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(row) {
    if (!confirm(`Hapus data "${row.name}"?`)) return;
    try {
        await api.remove(row.id);
        saved.value = saved.value.filter((r) => r.id !== row.id);
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(async () => {
    try {
        saved.value = await api.list();
    } catch (e) {
        error.value = e.message;
    }
});
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold">Prediksi Gender &amp; Kebangsaan</h1>
            <p class="text-sm text-slate-500">Sumber: genderize.io, nationalize.io, first.org</p>
        </header>

        <!-- Form pencarian -->
        <form class="flex gap-2" @submit.prevent="search">
            <input
                v-model="name"
                type="text"
                placeholder="Masukkan nama, misal: wahyu"
                class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none"
            />
            <button
                type="submit"
                :disabled="loading || !name.trim()"
                class="rounded-lg bg-indigo-600 px-5 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
                {{ loading ? 'Mencari…' : 'Cari' }}
            </button>
        </form>

        <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{{ error }}</p>
        <p v-if="notice" class="mt-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{{ notice }}</p>

        <!-- Hasil pencarian -->
        <section v-if="result" class="mt-6 rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold capitalize">{{ result.name }}</h2>
                <button
                    :disabled="saving"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    @click="save"
                >
                    {{ saving ? 'Menyimpan…' : 'Simpan ke Database' }}
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Gender</p>
                    <p class="mt-1 text-lg font-medium">{{ genderLabel(result.gender) }}</p>
                    <p class="text-sm text-slate-500">
                        Probabilitas {{ pct(result.gender_probability) }} · {{ result.gender_count.toLocaleString('id-ID') }} sampel
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Negara teratas</p>
                    <p class="mt-1 text-lg font-medium">{{ result.country_name ?? 'Tidak diketahui' }}</p>
                    <p class="text-sm text-slate-500">
                        {{ result.country_region ?? '-' }} · Probabilitas {{ pct(result.country_probability) }}
                    </p>
                </div>
            </div>

            <div v-if="result.countries.length" class="mt-4">
                <p class="mb-2 text-sm font-medium text-slate-600">Semua kemungkinan negara</p>
                <ul class="space-y-2">
                    <li v-for="c in result.countries" :key="c.country_id" class="text-sm">
                        <div class="flex justify-between">
                            <span>{{ c.country_name }} ({{ c.country_id }})</span>
                            <span class="text-slate-500">{{ pct(c.probability) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-slate-100">
                            <div class="h-2 rounded bg-indigo-500" :style="{ width: pct(c.probability) }"></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Data tersimpan -->
        <section class="mt-10">
            <h2 class="mb-3 text-lg font-semibold">Data Tersimpan ({{ saved.length }})</h2>
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Gender</th>
                            <th class="px-4 py-3">Negara</th>
                            <th class="px-4 py-3">Region</th>
                            <th class="px-4 py-3">Disimpan</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!saved.length">
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada data.</td>
                        </tr>
                        <tr v-for="row in saved" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium capitalize">{{ row.name }}</td>
                            <td class="px-4 py-3">{{ genderLabel(row.gender) }} ({{ pct(row.gender_probability) }})</td>
                            <td class="px-4 py-3">{{ row.country_name ?? '-' }} ({{ pct(row.country_probability) }})</td>
                            <td class="px-4 py-3">{{ row.country_region ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ fmtDate(row.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-red-600 hover:underline" @click="remove(row)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
