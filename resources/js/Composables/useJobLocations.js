// Route labels for a Jobs Queue row: "Airport (HIA) → Hotel (BEST PLAZA)" from its flight, hotel, venue or training ground.

function formatLocationWithAirport(location, airportCode) {
  if (!location || !airportCode) return location || "—";

  // Check if location already contains the airport code
  if (location.toUpperCase().includes(airportCode.toUpperCase())) {
    return location;
  }
  
  // Add airport code in parentheses
  return `${location} (${airportCode})`;
}

function formatLocationWithHotel(location, hotelName) {
  if (!location || !hotelName) return location || "—";

  // Check if location already contains the hotel name
  if (location.toLowerCase().includes(hotelName.toLowerCase())) {
    return location;
  }

  const hotelTerms = ["hotel", "team hotel"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a hotel reference
  if (hotelTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${hotelName})`;
  }

  return location;
}

function formatLocationWithVenue(location, venueName) {
  if (!location || !venueName) return location || "—";

  // Check if location already contains the venue name
  if (location.toLowerCase().includes(venueName.toLowerCase())) {
    return location;
  }

  const venueTerms = ["stadium", "venue", "ground", "arena"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a venue reference
  if (venueTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${venueName})`;
  }

  return location;
}

function formatLocationWithTrainingGround(location, trainingGroundName) {
  if (!location || !trainingGroundName) return location || "—";

  // Check if location already contains the training ground name
  if (location.toLowerCase().includes(trainingGroundName.toLowerCase())) {
    return location;
  }

  const trainingTerms = ["training", "training ground", "practice"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a training reference
  if (trainingTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${trainingGroundName})`;
  }

  return location;
}

export function formatJobFromLocation(job) {
  if (!job) return "—";
  
  let location = job.from || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    let airportCode = null;
    
    // For arrival movements, use destination airport
    if (job.kind === "arrival") {
      airportCode = job.flight?.destination_airport || 
                   job.team_data?.destination_airport;
    }
    // For departure movements, use origin airport
    else if (job.kind === "departure") {
      airportCode = job.flight?.origin_airport || 
                   job.team_data?.origin_airport;
    }
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = job.accommodation?.hotel_name || 
                     job.team_data?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = job.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    const trainingGround = job.team_data?.training_ground;
    if (trainingGround) {
      location = formatLocationWithTrainingGround(location, trainingGround);
    }
  }
  
  return location;
}

export function formatJobToLocation(job) {
  if (!job) return "—";
  
  let location = job.to || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    // For both arrival and departure, to_location typically uses destination airport
    const airportCode = job.flight?.destination_airport || 
                       job.team_data?.destination_airport;
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = job.accommodation?.hotel_name || 
                     job.team_data?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = job.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    const trainingGround = job.team_data?.training_ground;
    if (trainingGround) {
      location = formatLocationWithTrainingGround(location, trainingGround);
    }
  }
  
  return location;
}
