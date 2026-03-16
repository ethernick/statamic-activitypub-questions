<template>
    <a href="#" @click.prevent="createPoll" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-dark-600">
        New Poll
    </a>
</template>

<script>
export default {
    name: 'InboxNewPoll',
    methods: {
        createPoll() {
            Statamic.$activitypub.bus.$emit('activitypub:inbox:create-poll');
        }
    }
}
</script>
