<?php
// expects $tickets from parent

while ($row = $tickets->fetch_assoc()): ?>
    <div class="bg-gray-800 p-4 rounded border border-gray-700">
        <div class="flex justify-between cursor-pointer" onclick="toggleTicket(<?php echo $row['id']; ?>)">
            <div>
                <div class="flex items-center gap-3">
                    <h3 class="font-bold text-lg text-white"><?php echo htmlspecialchars($row['subject']); ?></h3>
                    <span class="px-2 py-0.5 rounded text-xs uppercase font-bold 
                        <?php echo $row['status']=='open'?'bg-green-500 text-black':($row['status']=='closed'?'bg-gray-600':'bg-blue-500'); ?>">
                        <?php echo $row['status']; ?>
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-1">From: <span class="text-blue-300"><?php echo htmlspecialchars($row['company_name']); ?></span> (<?php echo $row['company_email']; ?>)</p>
            </div>
        </div>

        <!-- Hidden Details (Expandable) -->
        <div id="ticket-<?php echo $row['id']; ?>" class="ticket-details hidden mt-4 border-t border-gray-700 pt-4" data-id="<?php echo $row['id']; ?>">
            <?php
            $tid = $row['id'];
            // Warning: Loop inside loop query. For low scale OK. For high scale, optimize later.
            $replies = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id=$tid ORDER BY created_at ASC");
            while($rep = $replies->fetch_assoc()):
            ?>
                <div class="mb-3 p-3 rounded <?php echo $rep['sender_type']=='admin'?'bg-blue-900/30 border border-blue-800 ml-10':'bg-gray-700/50 mr-10'; ?>">
                    <p class="text-xs font-bold mb-1 text-gray-400"><?php echo ucfirst($rep['sender_type']); ?></p>
                    <p class="text-white"><?php echo nl2br(htmlspecialchars($rep['message'])); ?></p>
                </div>
            <?php endwhile; ?>

            <!-- Reply Area -->
            <?php if($row['status'] !== 'closed'): ?>
            <form method="POST" class="mt-4 flex gap-2">
                <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                <input type="text" name="reply_message" class="flex-1 bg-gray-900 border border-gray-600 rounded p-2 text-white" placeholder="Type reply..." required>
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded">Reply</button>
            </form>
            <form method="POST" class="mt-2 text-right">
                <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                <input type="hidden" name="close_ticket" value="1">
                <button type="submit" class="text-red-400 text-sm hover:underline">Close Ticket</button>
            </form>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm italic mt-2">Ticket is closed.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endwhile; ?>
