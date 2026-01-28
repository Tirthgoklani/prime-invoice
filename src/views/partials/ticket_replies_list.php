<?php
// src/views/partials/ticket_replies_list.php
// Expects $replies result object from the parent script

while ($rep = $replies->fetch_assoc()): ?>
    <div class="flex flex-col <?php echo $rep['sender_type'] === 'user' ? 'items-end' : 'items-start'; ?>">
        <div class="max-w-[80%] <?php echo $rep['sender_type'] === 'user' ? 'bg-blue-600' : 'bg-gray-700'; ?> p-4 rounded-lg shadow">
            <p class="text-sm font-bold mb-1 opacity-75"><?php echo $rep['sender_type'] === 'user' ? 'You' : 'Admin Support'; ?></p>
            <div class="text-white whitespace-pre-wrap"><?php echo htmlspecialchars($rep['message']); ?></div>
        </div>
        <span class="text-xs text-gray-500 mt-1"><?php echo date("M d, H:i", strtotime($rep['created_at'])); ?></span>
    </div>
<?php endwhile; ?>
