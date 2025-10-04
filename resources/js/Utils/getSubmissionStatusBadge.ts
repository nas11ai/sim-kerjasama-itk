import { BadgeVariant } from "@/types/BadgeVariant";
import { CheckCircle, AlertCircle, Search, Clock, XCircle } from "lucide-vue-next";

interface SubmissionStatusBadge {
    text: string;
    variant: BadgeVariant;
    icon: any;
}

export const getSubmissionStatusBadge = (status: string): SubmissionStatusBadge => {
    switch (status) {
        case "approved":
            return { text: "Disetujui", variant: "default", icon: CheckCircle };
        case "pending":
            return { text: "Menunggu Review", variant: "outline", icon: Clock };
        case "under_review":
            return { text: "Sedang Direview", variant: "secondary", icon: Search };
        case "needs_revision":
            return { text: "Perlu Revisi", variant: "outline", icon: AlertCircle };
        case "rejected":
            return { text: "Ditolak", variant: "destructive", icon: XCircle };
        default:
            return { text: "Unknown", variant: "outline", icon: AlertCircle };
    }
};
