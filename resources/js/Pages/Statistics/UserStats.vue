<script setup lang="ts">
import Badge from "@/Components/ui/badge/Badge.vue";
import Button from "@/Components/ui/button/Button.vue";
import Card from "@/Components/ui/card/Card.vue";
import CardContent from "@/Components/ui/card/CardContent.vue";
import CardDescription from "@/Components/ui/card/CardDescription.vue";
import CardHeader from "@/Components/ui/card/CardHeader.vue";
import CardTitle from "@/Components/ui/card/CardTitle.vue";
import BarChart from "@/Components/ui/chart-bar/BarChart.vue";
import DonutChart from "@/Components/ui/chart-donut/DonutChart.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import {
    AlertCircle,
    BarChart3,
    ChartColumn,
    Clock,
    PieChartIcon,
    User,
    Users,
} from "lucide-vue-next";
import { Pagination } from "reka-ui/namespaced";
import { ref } from "vue";

interface UserRecent {
    id: number;
    name: string;
    email: string;
    created_at: string;
    is_reviewer: boolean;
}

const props = defineProps<{
    userRecent: UserRecent[];
    totalUsers: number;
    totalAdmin: number;
    totalNonAdmin: number;
    totalProdi: number;
    totalFaculty: number;
}>();

const chartType = ref<"bar" | "donut">("donut");

const recentUserTotal = props.userRecent.length;
const data = [
    { name: "User Admin", total: props.totalAdmin },
    { name: "User Reguler", total: props.totalNonAdmin },
    { name: "User Fakultas", total: props.totalFaculty },
    { name: "User Program Studi", total: props.totalProdi },
];
</script>

<template>
  <Head title="Statistik User" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2
            class="text-xl font-semibold leading-tight text-gray-800"
          >
            Statistik User
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            @click="router.visit(route('admin.stats.index'))"
          >
            <ChartColumn class="h-4 w-4" />
            Statistik
          </Button>
          <Button @click="router.visit(route('admin.users.index'))">
            <User class="h-4 w-4" />
            Manajemen User
          </Button>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <div
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4"
      >
        <Card
          class="border-l-4 border-l-gray-500 hover:shadow-lg transition-shadow"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Total User
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 mt-1"
                >
                  {{ totalUsers }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua User
                </p>
              </div>
              <div class="h-10 w-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-gray-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card
          class="border-l-4 border-l-purple-500 hover:shadow-lg transition-shadow"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Total User Admin
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 mt-1"
                >
                  {{ totalAdmin }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua User Admin
                </p>
              </div>
              <div class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-purple-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card
          class="border-l-4 border-l-blue-500 hover:shadow-lg transition-shadow"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Total User Reguler
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 mt-1"
                >
                  {{ totalNonAdmin }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua User Reguler
                </p>
              </div>
              <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card
          class="border-l-4 border-l-green-500 hover:shadow-lg transition-shadow"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Total User Fakultas
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 mt-1"
                >
                  {{ totalFaculty }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua User Fakultas
                </p>
              </div>
              <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-green-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card
          class="border-l-4 border-l-orange-500 hover:shadow-lg transition-shadow"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium text-gray-500 uppercase">
                  Total User Program Studi
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 mt-1"
                >
                  {{ totalProdi }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Semua User Program Studi
                </p>
              </div>
              <div class="h-10 w-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <Users class="h-5 w-5 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center"
                >
                  <Clock class="h-5 w-5 text-blue-600" />
                </div>
                <div>
                  <CardTitle>
                    User Terbaru (24 Jam Terakhir)
                  </CardTitle>
                  <CardDescription>
                    Aktivitas pembuatan user terbaru
                  </CardDescription>
                </div>
              </div>
              <Badge
                variant="outline"
                class="text-sm"
              >
                {{ recentUserTotal }} total users
              </Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div
              v-if="recentUserTotal === 0"
              class="text-center text-gray-500 py-6"
            >
              Tidak ada aktivitas user terbaru dalam 24 jam terakhir.
            </div>
            <div
              v-else
              class="space-y-4 max-h-96 overflow-y-auto p-2"
            >
              <div
                v-for="user in userRecent"
                :key="user.id"
                class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
              >
                <div
                  class="flex items-center gap-4 justify-between"
                >
                  <div>
                    <p class="font-medium text-gray-900">
                      {{ user.name }}
                    </p>
                    <p class="text-sm text-gray-500">
                      {{ user.email }}
                    </p>
                  </div>
                  <div class="text-sm text-gray-400">
                    {{
                      new Date(
                        user.created_at
                      ).toLocaleString()
                    }}
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <div class="flex flex-row gap-3 items-center">
                <div
                  class="h-10 w-10 bg-purple-100 rounded-lg flex items-center justify-center"
                >
                  <ChartColumn
                    class="h-5 w-5 text-purple-600"
                  />
                </div>
                <div>
                  <CardTitle>
                    Distribusi Grafik User
                  </CardTitle>
                  <CardDescription>Rincian</CardDescription>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  :class="{
                    'bg-gray-100': chartType === 'donut',
                  }"
                  @click="chartType = 'donut'"
                >
                  <PieChartIcon class="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :class="{
                    'bg-gray-100': chartType === 'bar',
                  }"
                  @click="chartType = 'bar'"
                >
                  <BarChart3 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div
              v-if="data.length === 0"
              class="text-center py-12"
            >
              <AlertCircle
                class="h-16 w-16 mx-auto text-gray-300 mb-4"
              />
              <p class="text-gray-500">
                Tidak ada data status tersedia
              </p>
            </div>
            <div v-else>
              <div
                class="flex justify-center items-center h-full"
              >
                <DonutChart
                  v-if="chartType === 'donut'"
                  index="name"
                  category="total"
                  :data="data"
                  :colors="[
                    '#7C3AED',
                    '#3B82F6',
                    '#10B981',
                    '#F59E0B',
                  ]"
                  :value-formatter="
                    (valueFormatter) =>
                      `Total ${valueFormatter} Users`
                  "
                  class="h-[256px]"
                />
                <BarChart
                  v-if="chartType === 'bar'"
                  :data="data"
                  index="name"
                  :categories="['total']"
                  :y-formatter="
                    (valueFormatter) =>
                      `${valueFormatter} Users`
                  "
                />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
