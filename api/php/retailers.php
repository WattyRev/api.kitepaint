<?php
http_response_code(410);
echo json_encode(array('message' => 'This endpoint has been removed.'));
