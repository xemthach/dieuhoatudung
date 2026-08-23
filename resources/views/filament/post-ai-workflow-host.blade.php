@if(!empty($postId))
    <livewire:ai-post-workflow-panel :post-id="(int) $postId" :key="'ai-post-workflow-'.$postId" />
@endif
