<?php
$amenitySlides = [
 ['assets/images/metro_court_view1.jpg','Indoor Courts','A look inside MetroAsia Arena and its multi-sport court setup.'],
 ['assets/images/metro_court_view2.jpg','Court View','Spacious covered courts prepared for scheduled games and reservations.'],
 ['assets/images/metro_court_view3.jpg','Playing Area','Dedicated playing areas designed for organized and convenient court use.'],
 ['assets/images/lounge1.jpg','Player Lounge','A comfortable lounge area where players and guests can relax.'],
 ['assets/images/lounge2.jpg','Lounge Area','Additional seating and waiting space inside the arena.'],
 ['assets/images/mezzanine1.jpg','Mezzanine','An elevated venue area with another perspective of the arena.'],
 ['assets/images/parking1.jpg','Parking','On-site parking space for players and arena visitors.'],
 ['assets/images/outside_view1.jpg','Arena Exterior','The exterior view of MetroAsia Arena for easier arrival and identification.'],
];
?>
<section id="amenities" class="metro-section metro-amenities-section">
  <div class="metro-container">
    <div class="metro-amenities-heading">
      <div>
        <span class="metro-eyebrow">Amenities &amp; Venue</span>
        <h2>Explore MetroAsia Arena</h2>
        <p>Take a closer look at the courts and venue amenities available to players and guests.</p>
      </div>
      <div class="metro-amenities-controls">
        <button type="button" class="metro-amenities-arrow" data-amenities-prev aria-label="Previous photos">←</button>
        <button type="button" class="metro-amenities-arrow" data-amenities-next aria-label="Next photos">→</button>
      </div>
    </div>

    <div class="metro-amenities-viewport" data-amenities-slider>
      <div class="metro-amenities-track">
        <?php foreach ($amenitySlides as $index => [$image,$title,$description]): ?>
          <article class="metro-amenity-card" data-amenity-slide>
            <button type="button" class="metro-amenity-image-button"
              data-amenity-lightbox
              data-image="<?php echo htmlspecialchars(app_url($image)); ?>"
              data-title="<?php echo htmlspecialchars($title); ?>">
              <img src="<?php echo htmlspecialchars(app_url($image)); ?>"
                   alt="<?php echo htmlspecialchars($title); ?>"
                   loading="<?php echo $index > 2 ? 'lazy' : 'eager'; ?>">
            </button>
            <div class="metro-amenity-card-copy">
              <span><?php echo str_pad((string)($index+1),2,'0',STR_PAD_LEFT); ?></span>
              <div>
                <h3><?php echo htmlspecialchars($title); ?></h3>
                <p><?php echo htmlspecialchars($description); ?></p>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<div class="metro-amenity-lightbox hidden" data-amenity-modal aria-hidden="true">
  <button type="button" class="metro-amenity-lightbox-close" data-amenity-close aria-label="Close photo">×</button>
  <div class="metro-amenity-lightbox-dialog">
    <img src="" alt="" data-amenity-modal-image>
    <p data-amenity-modal-title></p>
  </div>
</div>
