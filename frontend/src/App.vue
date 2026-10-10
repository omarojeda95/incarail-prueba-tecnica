<script setup lang="ts">
import { RouterView } from 'vue-router'
import NoticeStack from '@/components/NoticeStack.vue'
</script>

<template>
  <v-app>
    <RouterView />
    <NoticeStack />
  </v-app>
</template>
