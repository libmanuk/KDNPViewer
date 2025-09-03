<?php
// KDNP Viewer
// University of Kentucky Libraries

// === Configuration ===
define('REDIRECT_404', '404.html');
define('REDIRECT_HOME', 'https://sandbox01-na.primo.exlibrisgroup.com/discovery/search?vid=01SAA_UKY:KDNP&lang=en');
define('CONFIG_PATH', 'config/config.xml');
define('META_BASE_PATH', __DIR__ . '/meta/');
define('PV_BASE_PATH', __DIR__ . '/pv/');

// === Helper Functions ===
function redirectTo404() {
    header("Location: " . REDIRECT_404);
    exit;
}

function isValidArk(string $ark): bool {
    return preg_match('/^[a-z]{3}[0-9]{10}$/', $ark);
}

function sanitize($input): string {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function getConfigArray(SimpleXMLElement $xml, string $tag): array {
    $string = trim((string) $xml->$tag);
    return array_filter(array_map('trim', explode(',', $string)));
}

// === Redirect if just base URL is accessed without the script name or query ===
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$queryString = $_SERVER['QUERY_STRING'] ?? '';

if (($requestUri === '/' || $requestUri === '') && $queryString === '') {
    header("Location: " . REDIRECT_HOME);
    exit;
}

// === Redirect if script is accessed without an ID parameter ===
if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    header("Location: " . REDIRECT_HOME);
    exit;
}

// === Validate & Sanitize Input ===
if (!isset($_GET['id']) || !isValidArk($_GET['id'])) {
    redirectTo404("Invalid ARK ID");
}

$ark = sanitize($_GET['id']);
$ttl = substr($ark, 0, 3);

if (!preg_match('/^[a-z]{3}$/', $ttl)) {
    redirectTo404("Invalid TTL format");
}

// === Load Configuration ===
$config_meta = @file_get_contents(CONFIG_PATH) or redirectTo404();
$config_xml = @simplexml_load_string($config_meta) or redirectTo404();
$history_array = getConfigArray($config_xml, 'histories');

// === Validate Optional Query ===
$query = $_GET['q'] ?? '';
$query = (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $query)) ? trim($query) : '';

// === Load Metadata XML ===
$metaPath = META_BASE_PATH . "$ttl/$ark.xml";
if (!file_exists($metaPath) || !is_readable($metaPath)) {
    redirectTo404();
}
$xml = @simplexml_load_file($metaPath) or redirectTo404();

if (empty($xml->Pages)) {
    redirectTo404();
}

// === Secure Path Handling ===
$relativePath = "$ttl/$ark";
$directory = realpath(PV_BASE_PATH . $relativePath);
if ($directory === false || strpos($directory, realpath(PV_BASE_PATH)) !== 0) {
    redirectTo404();
}

// === Locate First Existing Page ===
$basePath = 'pv/' . urlencode($ttl) . '/' . urlencode($ark) . '/';
$pageNum = 1;
do {
    $filePath = $basePath . 'page_' . $pageNum . '.pdf';
    $fullPath = __DIR__ . '/' . $filePath;
    $pageNum++;
} while (!file_exists($fullPath));
$pageNum--; // last valid

// === Build Viewer Embed Path ===
$embed = 'vwp.html?file=' . $basePath . 'page_' . $pageNum . '.pdf#zoom=page-fit';

// === Process Search Term ===
$searchTerm = '';
if (
    $query &&
    strpos($query, '-') === false &&
    stripos($query, 'Newspaper issue') === false &&
    !preg_match('/lds(05|03|18),|title,|creator,|sub,/i', $query)
) {
    $searchTerm = $query;
}

$extraParams = '&zoom=page-fit&wholeword=true';
if (stripos($searchTerm, 'any,exact,') === 0) {
    $searchTerm = substr($searchTerm, strlen('any,exact,'));
    $extraParams = '&wholeword=true&zoom=page-fit';
} elseif (stripos($searchTerm, 'any,contains,') === 0) {
    $searchTerm = substr($searchTerm, strlen('any,contains,'));
}

// === Search Matching Text Files ===
$foundInFiles = [];
$txtFiles = glob($directory . '/page_*.txt') ?: [];
natsort($txtFiles);

if ($searchTerm) {
    $searchTerm = substr($searchTerm, 0, 100); // limit to 100 characters

    foreach ($txtFiles as $file) {
        if (filesize($file) > 1024 * 1024) continue; // skip files >1MB
            if (stripos(file_get_contents($file), $searchTerm) !== false) {
                $foundInFiles[] = $file;
        }
    }

    // If no full matches, search word-by-word
    if (empty($foundInFiles) && str_word_count($searchTerm) > 1) {
        $words = preg_split('/\s+/', $searchTerm);
        foreach ($txtFiles as $file) {
            $content = file_get_contents($file);
            foreach ($words as $word) {
                if (stripos($content, $word) !== false) {
                    $foundInFiles[] = $file;
                    break;
                }
            }
        }
    }
}

$hitsMap = array_flip($foundInFiles);
$first_file = reset($txtFiles);

// === HTML output ===
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KDNP Viewer</title>
  <link rel="stylesheet" href="css/local_v1_0.css">
</head>
<body>
<h1 class="visually-hidden">KDNP Viewer</h1>
<div class="form-row">
   <!-- SVG Toggle Switch on the Left -->
  <svg id="toggleSwitch" class="toggle-switch" onclick="toggleView()" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
    <rect x="2" y="6" width="19" height="11" rx="6" ry="6" stroke="black" fill="#ccc"/>
    <circle id="toggleCircle" cx="8" cy="12" r="4" fill="white"/>
  </svg>
  <div class="search-container">
      <form id="searchForm">
        <span id="searchInputs"><label for="q" class="visually-hidden">Search all images</label>
          <input type="text" placeholder="search all images" name="q" id="q" required value="<?= htmlspecialchars($searchTerm) ?>">
            <button type="submit" alt="Search" title="Search" id="srchimages" aria-label="Search">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.106a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
              </svg>
            </button>
          </span>

<?php

if ($searchTerm && count($foundInFiles) === 0): ?>
          &nbsp;<span id="noresults">No results for '<?= htmlspecialchars($searchTerm) ?>'.</span>
            <button id="closeNoResults" aria-label="Close">
              <svg width="13" height="13" viewBox="0 0 27 27" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            <style>#searchInputs {display: none;}</style>
            <script>
               // Close no results and show input/button again
               document.getElementById('closeNoResults').addEventListener('click', function (e) {
                 e.preventDefault();
                 document.getElementById('noresults').style.display = 'none';
                 document.getElementById('closeNoResults').style.display = 'none';
                 document.getElementById('searchInputs').style.display = 'inline-block';
               });
            </script>
      
        <?php endif; ?>
        
      </form>
    </div>
    <div class="dropdown-container">
      <div id="news-dropdown-container">
        <nav id="nav" aria-label="Newspaper page image menu">  
        <button id="prevButton" alt="Previous Page" title="Previous Page" type="button" onclick="navigate(-1)">&lt;</button>
          <label for="fileSelect" class="sr-only">Select an image</label>
            <select id="fileSelect"  aria-describedby="selectHelp" onchange="openPDF(this.value)">
              <option value="">-- Select an image: --</option>

      <?php
      $selectedSet = false; // Tracks if we've already selected an option

      foreach ($txtFiles as $file): 
          $filename = basename($file);
          $filename_without_extension = pathinfo($filename, PATHINFO_FILENAME);
          $pdfFilename = str_replace('.txt', '.pdf', $filename);
          $encodedSearchTerm = urlencode($searchTerm);

          if (isset($hitsMap[$file])) {
              $url = "vwp.html?file=pv/{$ttl}/{$ark}/{$pdfFilename}#?search={$encodedSearchTerm}{$extraParams}";
          } else {
              $url = "vwp.html?file=pv/{$ttl}/{$ark}/{$pdfFilename}#zoom=page-fit"; 
          }

          $filename_without_extension = str_replace('page_', '', $filename_without_extension);
          $label = $filename_without_extension . (isset($hitsMap[$file]) ? ' - results found' : '');

          // Determine if this option should be selected
          $isSelected = '';
          if (!$selectedSet && strpos($label, ' - results found') !== false) {
              $isSelected = ' selected';
              $selectedSet = true;
          }
      ?>
      
              <option aria-label="<?= htmlspecialchars($label) ?>" value="<?= htmlspecialchars($url) ?>"<?= $isSelected ?>><?= htmlspecialchars($label) ?></option>

      <?php endforeach; ?>

            </select>
              <div id="selectHelp" class="visually-hidden">Use the arrow keys to navigate and press Enter to open a PDF.</div>
      <button id="nextButton" alt="Next Page" title="Next Page" type="button" onclick="navigate(1)">&gt;</button>
      </nav>
  </div>
</div>

       <?php
        
        if (in_array($ttl, $history_array)) {
                        
    	  // URL of the XML file
    	  $url2 = 'https://kdnp.uky.edu/hist/' . urlencode($ttl) . '.xml';

    	  // Use file_get_contents to fetch the XML content
    	  $xmlContent2 = @file_get_contents($url2);

    	  // Load the XML content into a SimpleXMLElement object
    	  $xml2 = @simplexml_load_string($xmlContent2);

    	  if ($xml2 === FALSE) {
          // do nothing
    	  }

    	  // Access the 'history' element
    	  if (isset($xml2->history)) {
            $hist_content = $xml2->history;
    	  } else {
            $hist_content = "There was a problem retrieving this history.";
    	  }
      
        ?>    
                        
        <span id="about_toggle">                                            
          <a href="#" alt="About" title="About" class="toggle-link" onclick="toggleContent(event)">
            <svg width="24" height="24" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <!-- Circle outline -->
              <circle cx="50" cy="50" r="45" stroke="#0B51C1" stroke-width="6" fill="none" />

              <!-- Extra bold dot of the "i" -->
              <circle cx="50" cy="28" r="7" fill="#0B51C1" />

              <!-- Extra bold stem of the "i" -->
              <rect x="42" y="38" width="16" height="36" fill="#0B51C1" rx="3" />
            </svg>
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7L8 5z" fill="currentColor" /></svg></a>
        </span>

	<?php } ?>

</div>
<div id="toggle-content">
  <p><?php if (isset($hist_content)) { echo $hist_content; } ?></p>
</div>

<div id="full"></div>

<script>
   document.getElementById("full").innerHTML = 
  `<iframe src="<?php echo htmlspecialchars($embed, ENT_QUOTES, 'UTF-8'); ?>" 
           frameborder="0" 
           title="news viewer" 
           name="view" 
           id="fulls" 
           style="width:100%" 
           height="700" 
           allowfullscreen="true" 
           scrolling="no"></iframe>`;
   
    function openPDF(url) {
        if (url) {
            document.getElementById('fulls').src = url;
        }
    }
    
    function toggleContent(event) {
      event.preventDefault();
      const content = document.getElementById('toggle-content');
      const icon = document.querySelector('.icon');

      if (content) {
        const isVisible = content.style.display === 'block';
        content.style.display = isVisible ? 'none' : 'block';

        if (icon) {
          icon.classList.toggle('open', !isVisible);
        }
      }
    }
    
    function updateButtonVisibility() {
      const fileSelect = document.getElementById('fileSelect');
      const prevButton = document.getElementById('prevButton');
      const nextButton = document.getElementById('nextButton');
      const selectedIndex = fileSelect.selectedIndex;
      const totalOptions = fileSelect.options.length;

      // If "Select an image" is selected (first option), show "Next" button only
      if (selectedIndex === 0) {
        prevButton.style.display = 'none'; // Hide "Prev" button
        nextButton.style.display = 'inline-block'; // Show "Next" button
      }
      // If the second option (just after "Select an image") is selected, show "Next" button only
      else if (selectedIndex === 1) {
        prevButton.style.display = 'none'; // Hide "Prev" button
        nextButton.style.display = 'inline-block'; // Show "Next" button
      } 
      // If the last option is selected, show "Prev" button only
      else if (selectedIndex === totalOptions - 1) {
        prevButton.style.display = 'inline-block'; // Show "Prev" button
        nextButton.style.display = 'none'; // Hide "Next" button
      } 
      else {
        // Show both buttons when navigating through other options
        prevButton.style.display = 'inline-block';
        nextButton.style.display = 'inline-block';
      }
    }

    // Call the function to update the button visibility initially and when the dropdown changes
    document.getElementById('fileSelect').addEventListener('change', updateButtonVisibility);
    
    // Function to handle navigation
    function navigate(direction) {
    const select = document.getElementById('fileSelect');
    let currentIndex = select.selectedIndex;

    // Start at index 1 if placeholder is selected
    if (currentIndex === 0) currentIndex = 1;

    let newIndex = currentIndex + direction;

    // Clamp index within bounds
    newIndex = Math.max(1, Math.min(newIndex, select.options.length - 1));

    // Update selection
    select.selectedIndex = newIndex;

    // Trigger change event (updates UI/buttons) and open PDF
    select.dispatchEvent(new Event('change'));
    openPDF(select.value);
    }

    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault(); // prevent the form from submitting normally

        const query = document.getElementById('q').value.trim();
        const currentUrl = new URL(window.location.href);

        // Set or update the 'q' parameter
        currentUrl.searchParams.set('q', query);

        // Redirect to updated URL
        window.location.href = currentUrl.toString();
    });  
    
    const searchContainer = document.querySelector('.search-container');
    const dropdownContainer = document.querySelector('.dropdown-container');
    const toggleSwitch = document.getElementById('toggleSwitch');
    const searchForm = document.getElementById('searchForm');

    function updateVisibilityBasedOnScreen() {
      if (window.innerWidth < 780) {
        searchContainer.style.display = 'none';
        dropdownContainer.style.display = 'block';
        toggleSwitch.style.display = 'inline-block';
    } else {
        searchContainer.style.display = 'block';
        dropdownContainer.style.display = 'block';
        toggleSwitch.style.display = 'none';
        }
    }

    function toggleView() {
      const isSearchVisible = searchContainer.style.display === 'block';

      // Toggle containers
      searchContainer.style.display = isSearchVisible ? 'none' : 'block';
      dropdownContainer.style.display = isSearchVisible ? 'block' : 'none';

      // Move SVG toggle circle
      const circle = document.getElementById('toggleCircle');
      
      if (circle) {
        circle.setAttribute('cx', isSearchVisible ? '8' : '16');
      }
    }  

    // Handle form submission on small screens
    searchForm.addEventListener('submit', function (e) {
      if (window.innerWidth < 780) {
        setTimeout(() => {
          searchContainer.style.display = 'none';
          dropdownContainer.style.display = 'block';
          toggleSwitch.textContent = 'Show Search';
        }, 100); // Slight delay to avoid disrupting submission
      }
    });

    window.addEventListener('DOMContentLoaded', function () {
      updateButtonVisibility();
      updateVisibilityBasedOnScreen();

      const fileSelect = document.getElementById('fileSelect');
      if (fileSelect) {
      const selectedOption = fileSelect.options[fileSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
          // Trigger the onchange event manually
          fileSelect.dispatchEvent(new Event('change'));
        }
      }
    });

    // Listen to window resize
    window.addEventListener('resize', updateVisibilityBasedOnScreen);

    // Handle display of no results message in mobile view
    window.addEventListener('load', function () {
      // Delay execution to ensure other scripts have finished
      setTimeout(function () {
        // Only run if the screen width is 779px or less
        if (window.innerWidth > 779) return;

        let observer;

      function checkAndTrigger() {
        const noResults = document.getElementById('noresults');
        const toggleSwitch = document.getElementById('toggleSwitch');

        if (noResults && toggleSwitch) {
          const clickEvent = new Event('click', { bubbles: true });
          toggleSwitch.dispatchEvent(clickEvent);
          //console.log('#toggleSwitch click event dispatched.');
          if (observer) observer.disconnect();
        }
      }

      // Initial check
      checkAndTrigger();

      // Observe the DOM for dynamic addition of #noresults
      observer = new MutationObserver(checkAndTrigger);

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }, 0);
  });
</script>
</body>
</html>

