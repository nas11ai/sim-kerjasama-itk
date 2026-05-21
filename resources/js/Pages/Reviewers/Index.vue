<!-- resources\js\Pages\Reviewers\Index.vue -->
<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/Components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    UserCheck,
    UserX,
    Filter,
    AlertTriangle,
    X,
    CheckCircle,
    XCircle,
    ChevronsUpDown,
    Check,
    ChevronLeft,
    ChevronRight,
    Users,
    Calendar,
} from "lucide-vue-next";
import { useToast } from "@/Components/ui/toast/use-toast";
import { debounce } from "lodash";
import { cn } from "@/lib/utils";

interface ReviewerRole {
    id: number;
    name: string;
}

interface Reviewer {
    id: number;
    start_date: string;
    end_date: string | null;
    is_active: boolean;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    reviewer_role: {
        id: number;
        name: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    reviewers: {
        data: Reviewer[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    reviewerRoles: ReviewerRole[];
    filters: {
        search?: string;
        role?: string;
        status?: string;
    };
}

const props = defineProps<Props>();
const { toast } = useToast();

const searchQuery = ref(props.filters.search || "");
const selectedRole = ref(props.filters.role || "all");
const selectedStatus = ref(props.filters.status || "all");

const openStatus = ref(false);
const openRole = ref(false);

const showDeleteDialog = ref(false);
const reviewerToDelete = ref<Reviewer | null>(null);

const statusOptions = [
    { value: "all", label: "Semua Status" },
    { value: "active", label: "Aktif" },
    { value: "inactive", label: "Tidak Aktif" },
];

const selectedStatusLabel = computed(() => {
    const status = statusOptions.find((s) => s.value === selectedStatus.value);
    return status?.label || "Pilih status...";
});

const selectedRoleLabel = computed(() => {
    if (selectedRole.value === "all") return "Semua Role";
    const role = props.reviewerRoles.find(
        (r) => r.id.toString() === selectedRole.value
    );
    return role?.name || "Pilih role...";
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (searchQuery.value) count++;
    if (selectedRole.value !== "all") count++;
    if (selectedStatus.value !== "all") count++;
    return count;
});

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

watch([selectedRole, selectedStatus], () => {
    applyFilters();
});

const applyFilters = () => {
    const params: Record<string, any> = {};

    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedRole.value && selectedRole.value !== "all")
        params.role = selectedRole.value;
    if (selectedStatus.value && selectedStatus.value !== "all")
        params.status = selectedStatus.value;

    router.get(route("admin.reviewers.index"), params, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    searchQuery.value = "";
    selectedRole.value = "all";
    selectedStatus.value = "all";

    router.get(
        route("admin.reviewers.index"),
        {},
        {
            preserveState: true,
            replace: true,
        }
    );
};

const deleteReviewer = (reviewer: Reviewer) => {
    reviewerToDelete.value = reviewer;
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (reviewerToDelete.value) {
        router.delete(route("admin.reviewers.destroy", reviewerToDelete.value.id), {
            onSuccess: () => {
                showDeleteDialog.value = false;
                reviewerToDelete.value = null;
                toast({
                    title: "Sukses",
                    description: "Reviewer Berhasil dihapus!",
                });
            },
            onError: (errors) => {
                toast({
                    title: "Error",
                    description: errors.error || "Gagal menghapus reviewer.",
                    variant: "destructive",
                });
            },
            onFinish: () => {
                showDeleteDialog.value = false;
                reviewerToDelete.value = null;
            },
        });
    }
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    reviewerToDelete.value = null;
};

const toggleReviewerStatus = (reviewer: Reviewer) => {
    const isActive = reviewer.end_date === null;

    router.patch(
        route(
            isActive
                ? "admin.reviewers.deactivate"
                : "admin.reviewers.activate",
            reviewer.id
        ),
        {},
        {
            onSuccess: () => {
                toast({
                    title: "Sukses",
                    description: `Reviewer berhasil ${
                        isActive ? "dinonaktifkan" : "diaktifkan kembali"
                    }!`,
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "Gagal memperbarui status.",
                    variant: "destructive",
                });
            },
        }
    );
};


const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const hasFilters = computed(() => {
    return (
        searchQuery.value ||
        (selectedRole.value && selectedRole.value !== "all") ||
        (selectedStatus.value && selectedStatus.value !== "all")
    );
});

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: false,
        });
    }
};

const totalReviewers = computed(() => {
    return props.reviewers?.total || 0;
});

</script>

<template>
  <Head title="Reviewer Manajemen" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Reviewer Manajemen
          </h2>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('admin.reviewer-roles.index')">
            <Button variant="outline">
              <Filter class="h-4 w-4 mr-2" />
              Role Reviewer
            </Button>
          </Link>
          <Link :href="route('admin.reviewers.create')">
            <Button>
              <Plus class="h-4 w-4 mr-2" />
              Tambah Reviewer
            </Button>
          </Link>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Search and Filters -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-lg flex items-center gap-2">
              <Filter class="h-5 w-5" />
              Cari & Filter
              <Badge
                v-if="activeFiltersCount > 0"
                variant="secondary"
              >
                {{ activeFiltersCount }} aktif
              </Badge>
            </CardTitle>
            <Button
              v-if="hasFilters"
              variant="ghost"
              size="sm"
              @click="clearFilters"
            >
              <X class="h-4 w-4 mr-2" />
              Hapus Semua Filter
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col lg:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1">
              <div class="relative">
                <Search
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4"
                />
                <Input
                  v-model="searchQuery"
                  placeholder="Cari berdasarkan nama atau email..."
                  class="pl-10"
                />
              </div>
            </div>

            <!-- Role Filter -->
            <div class="w-full lg:w-56">
              <Popover v-model:open="openRole">
                <PopoverTrigger as-child>
                  <Button
                    variant="outline"
                    role="combobox"
                    :aria-expanded="openRole"
                    class="w-full justify-between"
                  >
                    <span class="truncate">{{ selectedRoleLabel }}</span>
                    <ChevronsUpDown
                      class="ml-2 h-4 w-4 shrink-0 opacity-50"
                    />
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[250px] p-0">
                  <Command>
                    <CommandInput
                      placeholder="cari role..."
                      class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                    />
                    <CommandList>
                      <CommandEmpty>Tidak ada role ditemukan.</CommandEmpty>
                      <CommandGroup>
                        <CommandItem
                          value="all"
                          @select="() => {
                            selectedRole = 'all';
                            openRole = false;
                          }"
                        >
                          <Check
                            :class="cn('mr-2 h-4 w-4',
                                       selectedRole === 'all'
                                         ? 'opacity-100'
                                         : 'opacity-0'
                            )"
                          />
                          Semua Role
                        </CommandItem>
                        <CommandItem
                          v-for="role in props.reviewerRoles"
                          :key="role.id"
                          :value="role.id.toString()"
                          @select="() => {
                            selectedRole = role.id.toString();
                            openRole = false;
                          }"
                        >
                          <Check
                            :class="cn('mr-2 h-4 w-4',
                                       selectedRole ===
                                         role.id.toString()
                                         ? 'opacity-100'
                                         : 'opacity-0'
                            )"
                          />
                          {{ role.name }}
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
            </div>

            <!-- Status Filter -->
            <div class="w-full lg:w-48">
              <Popover v-model:open="openStatus">
                <PopoverTrigger as-child>
                  <Button
                    variant="outline"
                    role="combobox"
                    :aria-expanded="openStatus"
                    class="w-full justify-between"
                  >
                    {{ selectedStatusLabel }}
                    <ChevronsUpDown
                      class="ml-2 h-4 w-4 shrink-0 opacity-50"
                    />
                  </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[200px] p-0">
                  <Command>
                    <CommandInput
                      placeholder="cari status..."
                      class="flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 border-0 ring-0 focus:ring-0 focus:outline-hidden"
                    />
                    <CommandList>
                      <CommandEmpty>Tidak ada status ditemukan.</CommandEmpty>
                      <CommandGroup>
                        <CommandItem
                          v-for="status in statusOptions"
                          :key="status.value"
                          :value="status.value"
                          @select="
                            () => {
                              selectedStatus = status.value;
                              openStatus = false;
                            }
                          "
                        >
                          <Check
                            :class="
                              cn(
                                'mr-2 h-4 w-4',
                                selectedStatus === status.value
                                  ? 'opacity-100'
                                  : 'opacity-0'
                              )
                            "
                          />
                          <div class="flex items-center gap-2">
                            <CheckCircle
                              v-if="status.value === 'active'"
                              class="h-4 w-4 text-green-600"
                            />
                            <XCircle
                              v-else-if="status.value === 'inactive'"
                              class="h-4 w-4 text-red-600"
                            />
                            {{ status.label }}
                          </div>
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Reviewers Table -->
      <Card>
        <CardHeader>
          <CardTitle>Reviewer ({{ totalReviewers }})</CardTitle>
          <CardDescription>
            Manajemen penugasan dan izin reviewer
          </CardDescription>
        </CardHeader>
        <CardContent>
          <!-- Empty State -->
          <div
            v-if="props.reviewers.data.length === 0"
            class="text-center py-12"
          >
            <UserCheck class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">
              Tidak ada Reviewer ditemukan
            </h3>
            <p class="text-sm text-muted-foreground mb-4">
              {{
                hasFilters
                  ? "Tidak ada reviewer yang cocok dengan kriteria pencarian Anda."
                  : "Mulailah dengan menambahkan reviewer pertama Anda."
              }}
            </p>
            <Link
              v-if="!hasFilters"
              :href="route('admin.reviewers.create')"
            >
              <Button>
                <Plus class="h-4 w-4 mr-2" />
                Tambah Reviewer Pertama
              </Button>
            </Link>
            <Button
              v-else
              variant="outline"
              @click="clearFilters"
            >
              <X class="h-4 w-4 mr-2" />
              Hapus Filter
            </Button>
          </div>

          <!-- Reviewer Cards List -->
          <div
            v-else
            class="space-y-4"
          >
            <div
              v-for="reviewer in props.reviewers.data"
              :key="reviewer.id"
              class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
            >
              <div class="flex items-center space-x-4 flex-1">
                <div class="shrink-0">
                  <div
                    class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"
                  >
                    <UserCheck class="h-6 w-6 text-blue-600" />
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="font-semibold text-gray-900 text-base">
                    {{ reviewer.user.name }}
                  </h4>
                  <p class="text-sm text-muted-foreground truncate">
                    {{ reviewer.user.email }}
                  </p>
                  <div class="flex items-center gap-2 mt-2 flex-wrap">
                    <Badge
                      variant="outline"
                      class="bg-purple-50 text-blue-700 border-purple-200"
                    >
                      {{ reviewer.reviewer_role.name }}
                    </Badge>
                    <Badge
                      :variant="
                        reviewer.is_active ? 'default' : 'destructive'
                      "
                      class="flex items-center gap-1"
                    >
                      {{ reviewer.is_active ? "Aktif" : "Nonaktif" }}
                    </Badge>
                  </div>
                </div>
              </div>

              <div class="flex items-center space-x-4">
                <div class="text-right text-sm text-muted-foreground hidden md:block">
                  <div class="flex items-center gap-2 justify-end mb-1">
                    <Calendar class="h-4 w-4" />
                    <span class="font-medium">Tanggal Mulai:</span>
                    <span>{{ formatDate(reviewer.start_date) }}</span>
                  </div>
                  <div
                    v-if="reviewer.end_date"
                    class="flex items-center gap-2 justify-end"
                  >
                    <Calendar class="h-4 w-4" />
                    <span class="font-medium">Tanggal Selesai:</span>
                    <span>{{ formatDate(reviewer.end_date) }}</span>
                  </div>
                  <div
                    v-else
                    class="text-green-600 font-medium"
                  >
                    Tidak ada tanggal selesai
                  </div>
                </div>

                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button
                      variant="ghost"
                      size="sm"
                    >
                      <MoreHorizontal class="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                      <Link
                        :href="
                          route('admin.reviewers.show', reviewer.id)
                        "
                      >
                        <Eye class="h-4 w-4 mr-2" />
                        Lihat Detail
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                      <Link
                        :href="
                          route('admin.reviewers.edit', reviewer.id)
                        "
                      >
                        <Edit class="h-4 w-4 mr-2" />
                        Edit
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="cursor-pointer"
                      @click="toggleReviewerStatus(reviewer)"
                    >
                      <UserX
                        v-if="reviewer.is_active"
                        class="h-4 w-4 mr-2"
                      />
                      <UserCheck
                        v-else
                        class="h-4 w-4 mr-2"
                      />
                      {{ reviewer.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="text-destructive cursor-pointer"
                      @click="deleteReviewer(reviewer)"
                    >
                      <Trash2 class="h-4 w-4 mr-2" />
                      Hapus
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Pagination -->
      <div
        v-if="props.reviewers.last_page > 1"
        class="flex justify-center mt-4"
      >
        <div class="flex items-center gap-2">
          <template
            v-for="link in props.reviewers.links"
            :key="link.label"
          >
            <Button
              v-if="link.url"
              variant="outline"
              size="sm"
              :class="{
                'bg-primary text-primary-foreground': link.active,
                'hover:bg-muted': !link.active,
              }"
              @click="goToPage(link.url)"
              v-html="link.label"
            />
            <span
              v-else
              :class="[
                'px-3 py-2 text-sm rounded-md text-muted-foreground',
                'bg-muted cursor-not-allowed',
              ]"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <AlertTriangle class="h-5 w-5 text-destructive" />
            Konfirmasi Hapus
          </DialogTitle>
          <DialogDescription>
            Apakah Anda yakin ingin menghapus reviewer ini? Tindakan ini tidak dapat dibatalkan.
          </DialogDescription>
        </DialogHeader>

        <div
          v-if="reviewerToDelete"
          class="py-4"
        >
          <div class="p-4 bg-muted rounded-lg">
            <div class="flex items-center gap-3">
              <div
                class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"
              >
                <UserCheck class="h-6 w-6 text-blue-600" />
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-lg">
                  {{ reviewerToDelete.user.name }}
                </h4>
                <p class="text-sm text-muted-foreground">
                  {{ reviewerToDelete.user.email }}
                </p>
                <div class="flex items-center gap-2 mt-2">
                  <Badge
                    variant="secondary"
                    class="text-xs"
                  >
                    {{ reviewerToDelete.reviewer_role.name }}
                  </Badge>
                  <Badge
                    :variant="
                      reviewerToDelete.is_active
                        ? 'default'
                        : 'destructive'
                    "
                    class="text-xs"
                  >
                    {{
                      reviewerToDelete.is_active ? "Active" : "Inactive"
                    }}
                  </Badge>
                </div>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            @click="cancelDelete"
          >
            Batal
          </Button>
          <Button
            variant="destructive"
            @click="confirmDelete"
          >
            Hapus Reviewer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AuthenticatedLayout>
</template>
