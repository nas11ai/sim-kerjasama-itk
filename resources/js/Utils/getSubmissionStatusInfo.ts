import { BadgeVariant } from "@/types/BadgeVariant";
import { CheckCircle, AlertCircle, Search, Clock, XCircle } from "lucide-vue-next";

interface StatusInfo {
    variant: BadgeVariant;
    text: string;
    icon: any;
    description: string;
}

export const getSubmissionStatusInfo = (status: string): StatusInfo => {
    switch (status) {
        case "approved":
            return {
                variant: "default",
                text: "Disetujui",
                icon: CheckCircle,
                description: "Submission ini telah disetujui dan pengguna bisa melanjutkan."
            };
        case "pending":
            return {
                variant: "outline",
                text: "Menunggu Review",
                icon: Clock,
                description: "Submission ini masih menunggu untuk direview."
            };
        case "under_review":
            return {
                variant: "secondary",
                text: "Sedang Direview",
                icon: Search,
                description: "Submission ini sedang dalam proses review."
            };
        case "needs_revision":
            return {
                variant: "outline",
                text: "Perlu Revisi",
                icon: AlertCircle,
                description: "Submission ini perlu direvisi oleh pengguna."
            };
        case "rejected":
            return {
                variant: "destructive",
                text: "Ditolak",
                icon: XCircle,
                description: "Submission ini ditolak."
            };
        default:
            return {
                variant: "outline",
                text: "Unknown",
                icon: AlertCircle,
                description: "Status submission tidak diketahui."
            };
    }
};
