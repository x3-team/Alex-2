<script setup>
/**
 * VPS merge hunk for production SiteSidebar.vue.
 * Do not overwrite the live sidebar (lab CTAs, quiz, modals) with this file.
 *
 * Production already imports useDoctorMode — keep that import, and change
 * openAudienceContent so doctors go to doctorsUrl('/materials') instead of
 * the old patient-site /doctor-materials path.
 */
import { router } from '@inertiajs/vue3';
import { useDoctorMode } from '@/Composables/useDoctorMode';

const emit = defineEmits(['close']);

const { isDoctorMode, doctorsUrl } = useDoctorMode();

const goTo = (path) => {
    router.visit(path);
};

const openAudienceContent = () => {
    if (isDoctorMode.value) {
        goTo(doctorsUrl('/materials'));
        emit('close');
        return;
    }

    goTo('/quiz');
};

defineExpose({ openAudienceContent, goTo });
</script>
