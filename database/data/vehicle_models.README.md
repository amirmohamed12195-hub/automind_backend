# Vehicle model catalog

`vehicle_models.json` is AutoMind's offline make/model snapshot. It contains
5,904 model nameplates across every one of the 184 supported makes. Model names
remain editable through the admin catalog, and API clients must also offer an
**Other model** option because new and regional nameplates can appear between
snapshot updates.

The snapshot combines and deduplicates:

- [VehiclesDB](https://vehiclesdb.com), pinned at commit
  `03039f8bbf3f0e6081f5c7e883aa5e319199c5ea` (CC-BY 4.0).
- [vehicle-makes-models](https://github.com/gor3a/vehicle-makes-models),
  pinned at the commit recorded in `_meta` (MIT).
- [global-car-models](https://github.com/serhatkildaci/global-car-models),
  pinned at commit `44da5c9e5e0f3162d65579033f7a641473308b11`
  (MIT).
- AutoMind's existing Egypt-focused catalog and a small supplement for marques
  absent from the upstream snapshots.

Required licenses, source notices, and the full VehiclesDB attribution are in
`vehicle_model_sources/`. Product surfaces using this data must include the
VehiclesDB credit described there in their About/Credits screen.
