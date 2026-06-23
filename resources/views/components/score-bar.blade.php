<div class="progress" style="height: 20px;">
    <div class="progress-bar {{ $score < 80 ? 'bg-danger' : 'bg-success' }}" 
         role="progressbar" 
         style="width: {{ $score }}%">
        {{ $score }}%
    </div>
</div>
