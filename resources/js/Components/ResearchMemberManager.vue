<script setup lang="ts">
/**
 * BR-SM-08 — anggota penelitian.
 *
 * Larangan konflik kepentingannya dua arah: orang yang sudah ditugaskan
 * sebagai reviewer pengajuan ini tidak bisa jadi anggotanya, dan sebaliknya.
 * Penolakannya datang dari backend — UI tidak mengulang aturannya, hanya
 * menampilkan alasannya apa adanya supaya keduanya tidak pernah menyimpang.
 *
 * Batas jumlah anggota berasal dari skema; sisa kuotanya ditampilkan supaya
 * peneliti tahu sebelum mencoba, bukan sesudah ditolak.
 */
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Label } from '@/Components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { Loader2, UserPlus, Users } from 'lucide-vue-next'
import { useToast } from '@/Components/ui/toast/use-toast'

interface Member {
    id: number
    name: string
    role: string
}

interface Candidate {
    id: number
    name: string
    email: string
}

interface Props {
    submissionId: number
    members: Member[]
    candidates: Candidate[]
    canManage: boolean
    /** null berarti skema tidak membatasi jumlah anggota. */
    maxMembers: number | null
}

const props = defineProps<Props>()

const { toast } = useToast()

const selectedUser = ref<string>('')
const selectedRole = ref<string>('member')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const ROLE_LABELS: Record<string, string> = {
    co_investigator: 'Co-Investigator',
    member: 'Anggota',
}

const remainingSlots = computed(() =>
    props.maxMembers === null ? null : Math.max(props.maxMembers - props.members.length, 0)
)

const isFull = computed(() => remainingSlots.value !== null && remainingSlots.value === 0)
const canSubmit = computed(() => selectedUser.value !== '' && !submitting.value && !isFull.value)

function addMember() {
    if (!canSubmit.value) return

    submitting.value = true
    errorMessage.value = null

    router.post(
        route('user.submissions.members.store', props.submissionId),
        { user_id: Number(selectedUser.value), role: selectedRole.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Anggota ditambahkan' })
                selectedUser.value = ''
            },
            onError: (errors) => {
                errorMessage.value = errors.user_id ?? 'Anggota gagal ditambahkan.'
            },
            onFinish: () => {
                submitting.value = false
            },
        }
    )
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center justify-between gap-2 text-base">
                <span class="flex items-center gap-2">
                    <Users class="h-4 w-4" />
                    Anggota Penelitian
                </span>
                <span
                    v-if="remainingSlots !== null"
                    class="text-sm font-normal text-muted-foreground"
                >
                    {{ members.length }} dari {{ maxMembers }}
                </span>
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="members.length" class="space-y-2">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
                >
                    <span class="text-sm">{{ member.name }}</span>
                    <Badge variant="outline">{{ ROLE_LABELS[member.role] ?? member.role }}</Badge>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Belum ada anggota selain ketua pengaju.
            </p>

            <template v-if="canManage">
                <p v-if="isFull" class="text-sm text-muted-foreground">
                    Jumlah anggota sudah mencapai batas skema. Keluarkan anggota lain sebelum
                    menambah.
                </p>

                <div v-else class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="member-user">Orang</Label>
                            <Select v-model="selectedUser">
                                <SelectTrigger id="member-user">
                                    <SelectValue placeholder="Cari nama" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="candidate in candidates"
                                        :key="candidate.id"
                                        :value="String(candidate.id)"
                                    >
                                        {{ candidate.name }} — {{ candidate.email }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label for="member-role">Peran</Label>
                            <Select v-model="selectedRole">
                                <SelectTrigger id="member-role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="co_investigator">Co-Investigator</SelectItem>
                                    <SelectItem value="member">Anggota</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <Button :disabled="!canSubmit" @click="addMember">
                        <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                        <UserPlus v-else class="mr-2 h-4 w-4" />
                        Tambah Anggota
                    </Button>

                    <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
