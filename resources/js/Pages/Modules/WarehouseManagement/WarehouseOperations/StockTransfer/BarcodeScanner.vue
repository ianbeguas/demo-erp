

<template>
  <div class="relative">
    <div id="reader" class="w-full max-w-xs mx-auto"></div>
    
    <!-- Flash Overlay for Scan Feedback -->
    <div v-if="showFlash" class="absolute inset-0 bg-green-400 opacity-50 animate-flash"></div>

    <button @click="stopScanner" class="mt-2 bg-red-500 text-white px-3 py-1 rounded">Stop Scanner</button>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Html5Qrcode } from "html5-qrcode";

const props = defineProps({
    onScanSuccess: Function
});

let html5QrCode;
const showFlash = ref(false);
let beepAudio;

function preloadBeep() {
    beepAudio = new Audio('/storage/sound/beep.mp3');
}

function playBeep() {
    if (beepAudio) {
        beepAudio.currentTime = 0; // rewind for rapid scans
        beepAudio.play().catch(err => console.error("Audio play failed:", err));
    }
}

function triggerFlash() {
    showFlash.value = true;
    setTimeout(() => { showFlash.value = false; }, 100);
}

onMounted(() => {
    preloadBeep();
    html5QrCode = new Html5Qrcode("reader");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            playBeep();
            triggerFlash();
            props.onScanSuccess(decodedText);
        },
        (errorMessage) => {
            // console.log(`Scan error: ${errorMessage}`);
        }
    ).catch(err => {
        console.error("Unable to start scanning.", err);
    });
});

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            console.log("Scanner stopped.");
        });
    }
}

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<style scoped>
@keyframes flash {
    0% { opacity: 0.5; }
    100% { opacity: 0; }
}

.animate-flash {
    animation: flash 0.1s ease-out;
}
</style>
