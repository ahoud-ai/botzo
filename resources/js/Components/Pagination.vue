<template>
    <div v-if="pagination && pagination.last_page > 1" class="ui-pagination-wrap">
      <div class="ui-pagination-group">
        <button type="button"
            :disabled="noPreviousPage"
            :class="{'opacity-50': noPreviousPage}"
            @click="loadPage(1)"
            class="ui-pagination-btn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
          </svg>
        </button>
        <button type="button"
            :disabled="noPreviousPage"
            :class="{'opacity-50': noPreviousPage}"
            @click="loadPage(pagination.current_page - 1)"
            class="ui-pagination-btn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
  
        <div class="ui-pagination-meta">
          <div class="ui-pagination-meta-number">{{ page }}</div>
          <!---<input type="number" @keydown.enter="loadPage(page)" v-model="page" class="px-2 w-11 h-11 text-center rounded border border-gray-400 shadow-sm lg:h-9 lg:w-9 lg:text-sm focus:ring-blue-500 focus:border-blue-500"/>-->
          <div class="ui-pagination-meta-label">{{ $t('of') }} {{ pagination.last_page }}</div>
        </div>
  
        <button type="button"
            :disabled="noNextPage"
            :class="{'opacity-50': noNextPage}"
            @click="loadPage(pagination.current_page + 1)"
            class="ui-pagination-btn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
  
        <button type="button"
            :disabled="noNextPage"
            :class="{'opacity-50': noNextPage}"
            @click="loadPage(pagination.last_page)"
            class="ui-pagination-btn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    name: 'Pagination',
    props: {
      pagination: Object,
    },
    data() {
      return {
        page: this.pagination?.current_page || 1
      }
    },
    watch: {
      'pagination.current_page': function(page) {
        if (page !== undefined) {
          this.page = page;
        }
      }
    },
    methods: {
      loadPage(page) {
        this.$inertia.get(this.$page.url, {page: page}, {
          preserveState: true
        });
      }
    },
    computed: {
      noPreviousPage() {
        return !this.pagination || this.pagination.current_page - 1 <= 0;
      },
      noNextPage() {
        return !this.pagination || this.pagination.current_page + 1 > this.pagination.last_page;
      }
    }
  };
  </script>
