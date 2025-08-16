<?php
// KDNP Viewer
// Copyright 2021-, University of Kentucky Libraries
//
// SAMPLE PHP Viewer URL: https://kdnp.uky.edu/?id=afr1900060101
// SAMPLE PDF.js URL: https://kdnp.uky.edu/vw.html?file=afr/afr19000060101/afr19000060101_text.pdf
// id pattern <three letter title code>+<YYYY>+<MM>+<DD>+<2 digit copy number>
// Production URL:  https://saalck-uky.primo.exlibrisgroup.com/discovery/search?vid=01SAA_UKY:KDNP
// Sandbox URL:  https://sandbox01-na.primo.exlibrisgroup.com/discovery/search?vid=01SAA_UKY:KDNP&lang=en

// config variables
$url = "404.html";
$redirect_home = "https://sandbox01-na.primo.exlibrisgroup.com/discovery/search?vid=01SAA_UKY:KDNP&lang=en";

// load config xml file
$config_meta = file_get_contents("config/config.xml");
($config_xml = simplexml_load_string($config_meta)) or die("Error: Cannot create object");

function getConfigArray(SimpleXMLElement $xml, string $tag): array {
    $string = trim((string) $xml->$tag);
    return array_filter(array_map('trim', explode(',', $string)));
}

$mode_array = getConfigArray($config_xml, 'viewermode');
$history_array = getConfigArray($config_xml, 'histories');

// Get the requested URL
$request_url = "https://" . $_SERVER['HTTP_HOST'] . rtrim($_SERVER['REQUEST_URI'], "/");

// Check if the URL already contains &q=
if (strpos($request_url, '&q=') === false) {
    // Append &q= regardless of other parameters
    $request_url .= '&q=';
}

// get valid id and generate page or redirect to homepage
if (isset($_GET["id"])) {
    $ark = $_GET["id"];

    // Check if the input is 13 characters long and starts with three lowercase alphanumeric characters
    if (strlen($ark) == 13 && preg_match('/^[a-z0-9]{3}/', $ark)) {
        // Save the first three characters as a new variable
        $ttl = substr($ark, 0, 3);
    } else {
        // Redirect to a fallback URL if ARK is invalid
        header("Location: $url");
        exit; 
    }

// long if statement that redirects to not found page    
if (isset($_GET["q"])) {
    $query = $_GET["q"];

    // Only keep $query if it does NOT match the YYYY-MM-DD pattern
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $query)) {
        // $query is valid and stays set
    } else {
        // $query matches the date pattern, so we unset it
        unset($query);
    }
}

    // Ensure proper validation and sanitization to prevent XSS or directory traversal attacks.
    $ark = htmlspecialchars($ark, ENT_QUOTES, 'UTF-8');
    $ttl = htmlspecialchars($ttl, ENT_QUOTES, 'UTF-8');
    
    // Check if the input is 13 characters long and starts with three lowercase alphanumeric characters
    if (strlen($ark) == 13 && preg_match('/^[a-z0-9]{3}/', $ark)) {
      // Save the first three characters as a new variable
      $ttl = substr($ark, 0, 3);

      $metaPath = "meta/$ttl/$ark.xml";

      if (!file_exists($metaPath) || !is_readable($metaPath)) {
        // Fallback: redirect to 404 or error page
        header("Location: $url");
        exit;
        }

      $meta = file_get_contents($metaPath);
      $xml = simplexml_load_string($meta);

      if ($xml === false) {
        // Handle invalid XML content gracefully
        header("Location: $url");
        exit;
        }

      // Extract metadata from the issue XML
        
      // Required elements
      $xml_pages = $xml->Pages;

      // Check if any of the variables are empty or null
      if (empty($xml_pages)) {

        // If any required variable is empty or null, redirect to the fallback URL
        header("Location: $url");
        exit; // Ensure no further code is executed
        }
        
          } else {
          // Redirect to a fallback URL if ARK is invalid
          header("Location: $url");
          exit; 
            }
    
      $baseDir = __DIR__ . '/pv/';
      $relativePath = $ttl . '/' . $ark;

        
      // Resolve absolute path securely
      $directory = rtrim(realpath($baseDir . $relativePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

      // Check if resolved path exists AND is within the base directory
      if ($directory === false || strpos($directory, realpath($baseDir)) !== 0) {
        // Invalid path or potential traversal detected
        header("Location: $url");
        exit;
            }
            
      // If there are missing pages ... can't assume page_1 exists
      $basePath = 'pv/' . urlencode($ttl) . '/' . urlencode($ark) . '/';

      $pageNum = 1;

      // Keep checking until the file exists
      do {
        $filePath = $basePath . 'page_' . $pageNum . '.pdf';
        $fullPath = __DIR__ . '/' . $filePath; // Adjust base dir as needed
        $pageNum++;
      } while (!file_exists($fullPath));

      // Go back one step because loop increments after check
      $pageNum--;

      // Build the final embed path
      $embed = 'vwp.html?file=' . $basePath . 'page_' . $pageNum . '.pdf#zoom=page-fit';
    		
      $searchTerm = (isset($_GET['q']) 
        && strpos($_GET['q'], '-') === false 
    	&& stripos($_GET['q'], 'Newspaper issue') === false) 
    	? trim($_GET['q']) 
    	: '';

      $extraParams = '&zoom=page-fit&wholeword=true';

      if (!empty($searchTerm)) {
        // Check if it starts with "any,exact" or "any,contains"
    	if (stripos($searchTerm, 'any,exact,') === 0) {
          // Remove "any,exact" from the beginning
          $searchTerm = trim(substr($searchTerm, strlen('any,exact,')));
          //&phrase=true can also be added to params
          $extraParams = '&wholeword=true&zoom=page-fit';
    	    } elseif (stripos($searchTerm, 'any,contains,') === 0) {
            // Remove "any,contains" from the beginning
            $searchTerm = trim(substr($searchTerm, strlen('any,contains,')));
            // No extra params added
        }
      }
            
      // Set array
      $foundInFiles = [];

      if ($searchTerm) {
        $txtFiles = glob($directory . 'page_*.txt');

      // First pass: try full search term
      foreach ($txtFiles as $file) {
        if (preg_match('/page_\d+\.txt$/', basename($file))) {
          $contents = file_get_contents($file);
          if (stripos($contents, $searchTerm) !== false) {
            $foundInFiles[] = $file;
          }
        }
      }

      // Second pass: if no results and term has multiple words
      if (empty($foundInFiles) && str_word_count($searchTerm) > 1) {
        $searchWords = preg_split('/\s+/', $searchTerm);
        foreach ($txtFiles as $file) {
          if (preg_match('/page_\d+\.txt$/', basename($file))) {
            $contents = file_get_contents($file);
              foreach ($searchWords as $word) {
                if (stripos($contents, $word) !== false) {
                  $foundInFiles[] = $file;
                    break; // Found at least one word, no need to check more
                  }
                }
          }
        }
      }
      } else {
               $txtFiles = glob($directory . 'page_*.txt');
      }
            
     natsort($txtFiles);  // Sort using "natural order"
     $hitsMap = array_flip($foundInFiles ?? []);
     $first_file = reset($txtFiles);
     $totalFiles = count($foundInFiles);

// Build HTML page
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KDNP Viewer</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f9;
        }

    h1 {
      color: #333;
        }

    .form-row {
      display: flex;
      align-items: center;
      gap: .3em;
      margin-top: 0em;
        }

     .search-container {
       flex: 1;
        }

      .dropdown-container {
        flex-shrink: 0;
        }

      label {
        font-weight: bold;
        }

      input[type="text"] {
        padding: 0.5em;
        width: 225px;
        height: 28px;
        }

      button {
        padding: 0.5em 1em;
        border: none;
        cursor: pointer;
        }

      button[type="submit"] {
        background-color: #0B51C1;
        color: white;
        margin-left: 0.5em;
        }

      button[type="button"] {
        background-color: #0B51C1;
        color: white;
        }

      button[type="button"]:hover {
        background-color: #bbb;
        }

      select {
        padding: 0.5em;
        }
        
      .toggle-link {
        display: flex;
        align-items: center;
        cursor: pointer;
        color: #337ab7;
        text-decoration: none;
        font-size: 18px;
       }

      .icon {
        margin-left: 2px;
        transition: transform 0.3s ease;
       }

       .icon.open {
         transform: rotate(90deg);
       }
       
       #srchimages, #prevButton, #nextButton {
         height: 44px;
         width: 44px;
       }
       
       #fileSelect {
         height: 44px;
       }

       #toggle-content {
         display: none;
         margin-top: 10px;
         padding: 10px;
         border: 1px solid #ccc;
         border-radius: 4px;
       }
       
      .toggle-switch {
        display: none;
        cursor: pointer;
        width: 40px;
        height: 30px;
        flex-shrink: 0;
        border: 1px solid;
        padding: 6px 6px 6px 0px;
       }
    
       #svgToggleSwitch {
         cursor: pointer;
         width: 50px;
         height: 30px;
         margin-right: 1em;
       }
       
       .sr-only {
         position: absolute;
         width: 1px;
         height: 1px;
         padding: 0;
         margin: -1px;
         overflow: hidden;
         clip: rect(0, 0, 0, 0);
         white-space: nowrap;
         border: 0;
        }
        
       .search-container {
         display: flex;
         align-items: center; /* Aligns items vertically centered */
         justify-content: flex-start; /* Aligns the form to the left */
        }

        #searchForm {
          display: flex;
          align-items: center; /* Aligns input and button vertically */
          }
          
        #news-dropdown-container {
          display: flex; 
          align-items: center; 
          gap: 10px;
          }
        
        #closeNoResults {
          border: none;
          background: transparent;
          cursor: pointer;
          margin-left: 10px;
          }
        
        /* Default: Show both on large screens */
        .search-container, .dropdown-container {
          display: block;
          }
        
        #toggle-content {
          display: none; 
          margin-top: 10px;
          font-size: 15px;
          }
        
        #noresults {
          border: 1px solid maroon;
          padding: 0px 8px 0px 8px;
          background-color: white;
          line-height: 40px;
          }
          
        #about_toggle {
          float:left;
          }
          
        .visually-hidden {
  	  position: absolute !important;
  	  height: 1px; width: 1px;
  	  overflow: hidden;
  	  clip: rect(1px, 1px, 1px, 1px);
  	  white-space: nowrap;
	}

        /* Small screen: hide search by default, show dropdown */
        @media (max-width: 779px) {
          .search-container {
            display: none;
          }

          .dropdown-container {
            display: block;
          }

          .toggle-switch {
            display: inline-block;
            margin: 10px 0;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: #0B51C1;
           }
          
          input[type="text"] {
            width: 140px;
          }
          
          .form-row {
	    justify-content: center;
	  }

	  .search-container {
	    flex: none;
	  }
	  
	  button[type="submit"] {
	    margin-left: 0px;
	  }
        }
    </style>
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

<?php
    
} else {
    // Fallback behavior goes here
    header("Location: $redirect_home");
}

?>

