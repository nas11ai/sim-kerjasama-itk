<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Alert from './AppAlert.vue'

interface AlertItem {
    id: string
    type: 'success' | 'error'
    title?: string
    message: string
    timeout?: number
}

// Define the flash message structure
interface FlashMessages {
    success?: string
    error?: string
    message?: string
    type?: 'success' | 'error'
    title?: string
}

const page = usePage()
const alerts = ref<AlertItem[]>([])

// Generate unique ID for alerts
const generateId = () => `alert_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`

// Add alert function
const addAlert = (alert: Omit<AlertItem, 'id'>) => {
    const newAlert: AlertItem = {
        ...alert,
        id: generateId(),
        timeout: alert.timeout ?? 5000, // Use nullish coalescing to handle undefined
    }

    alerts.value.push(newAlert)

    // Auto dismiss after timeout
    if (newAlert.timeout && newAlert.timeout > 0) {
        setTimeout(() => {
            dismissAlert(newAlert.id)
        }, newAlert.timeout)
    }
}

// Dismiss alert function
const dismissAlert = (id: string) => {
    const index = alerts.value.findIndex((alert) => alert.id === id)
    if (index > -1) {
        alerts.value.splice(index, 1)
    }
}

// Watch for flash messages from Laravel
watch(
    () => page.props.flash as FlashMessages | undefined,
    (flash) => {
        if (flash) {
            // Handle success message
            if (flash.success) {
                addAlert({
                    type: 'success',
                    title: 'Success',
                    message: flash.success,
                })
            }

            // Handle error message
            if (flash.error) {
                addAlert({
                    type: 'error',
                    title: 'Error',
                    message: flash.error,
                })
            }

            // Handle custom messages (if you want to extend later)
            if (flash.message) {
                addAlert({
                    type: flash.type || 'success',
                    title: flash.title,
                    message: flash.message,
                })
            }
        }
    },
    { deep: true, immediate: true }
)

// Watch for form errors from Inertia
watch(
    () => page.props.errors as Record<string, string> | undefined,
    (errors) => {
        if (errors && Object.keys(errors).length > 0) {
            // Show first error if it's a general error
            if (errors.error) {
                addAlert({
                    type: 'error',
                    title: 'Error',
                    message: errors.error,
                })
            }
        }
    },
    { deep: true, immediate: true }
)

// Expose addAlert function globally (optional)
onMounted(() => {
    // You can add this to window object if you want to trigger alerts from anywhere
    ;(window as any).addAlert = addAlert
})
</script>

<template>
    <!-- Fixed position alert container -->
    <div class="fixed top-4 right-4 z-50 w-full max-w-md space-y-2">
        <TransitionGroup name="alert" tag="div" class="space-y-2">
            <Alert
                v-for="alert in alerts"
                :key="alert.id"
                :type="alert.type"
                :title="alert.title"
                :message="alert.message"
                @dismiss="dismissAlert(alert.id)"
            />
        </TransitionGroup>
    </div>
</template>

<style scoped>
/* Transition animations */
.alert-enter-active {
    transition: all 0.3s ease-out;
}

.alert-leave-active {
    transition: all 0.3s ease-in;
}

.alert-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.alert-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.alert-move {
    transition: transform 0.3s ease;
}
</style>
