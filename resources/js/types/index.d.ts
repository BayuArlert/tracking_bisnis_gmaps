export interface Auth {
    user: User;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

// Google Maps Type Declarations
declare global {
    interface Window {
        google: typeof google;
    }
    
    const google: {
        maps: {
            Map: new (mapDiv: Element | null, opts?: google.maps.MapOptions) => google.maps.Map;
            Marker: new (opts?: google.maps.MarkerOptions) => google.maps.Marker;
            InfoWindow: new (opts?: google.maps.InfoWindowOptions) => google.maps.InfoWindow;
            LatLng: new (lat: number, lng: number) => google.maps.LatLng;
            LatLngBounds: new () => google.maps.LatLngBounds;
            Polygon: new (opts?: google.maps.PolygonOptions) => google.maps.Polygon;
            MapTypeId: {
                ROADMAP: string;
                SATELLITE: string;
                HYBRID: string;
                TERRAIN: string;
            };
            Animation: {
                BOUNCE: number;
                DROP: number;
            };
            SymbolPath: {
                CIRCLE: string;
                FORWARD_CLOSED_ARROW: string;
                FORWARD_OPEN_ARROW: string;
                BACKWARD_CLOSED_ARROW: string;
                BACKWARD_OPEN_ARROW: string;
            };
            marker: {
                AdvancedMarkerElement: new (opts?: google.maps.AdvancedMarkerOptions) => google.maps.AdvancedMarkerElement;
            };
        };
    };
    
    namespace google {
        namespace maps {
            interface Map {
                setCenter(latlng: LatLng | LatLngLiteral): void;
                setZoom(zoom: number): void;
                fitBounds(bounds: LatLngBounds): void;
                setMapTypeId(mapTypeId: string): void;
            }
            
            interface Marker {
                setMap(map: Map | null): void;
                setPosition(latlng: LatLng | LatLngLiteral): void;
                addListener(eventName: string, handler: () => void): void;
            }
            
            interface InfoWindow {
                open(map?: Map, anchor?: Marker): void;
                close(): void;
                setContent(content: string | Element): void;
            }
            
            interface MapOptions {
                center?: LatLng | LatLngLiteral;
                zoom?: number;
                mapTypeId?: string;
                mapTypeControl?: boolean;
                streetViewControl?: boolean;
                fullscreenControl?: boolean;
                zoomControl?: boolean;
                gestureHandling?: string;
            }
            
            interface MarkerOptions {
                position?: LatLng | LatLngLiteral;
                map?: Map;
                title?: string;
                animation?: number;
                icon?: string | Icon | Symbol;
                label?: string | MarkerLabel;
            }
            
            interface MarkerLabel {
                text: string;
                color?: string;
                fontSize?: string;
                fontWeight?: string;
            }
            
            interface AdvancedMarkerElement {
                position?: LatLng | LatLngLiteral;
                map?: Map;
                title?: string;
                content?: Element;
            }
            
            interface AdvancedMarkerOptions {
                position?: LatLng | LatLngLiteral;
                map?: Map;
                title?: string;
                content?: Element;
            }
            
            interface InfoWindowOptions {
                content?: string | Element;
                position?: LatLng | LatLngLiteral;
            }
            
            interface PolygonOptions {
                paths?: LatLng[] | LatLngLiteral[];
                map?: Map;
                strokeColor?: string;
                strokeOpacity?: number;
                strokeWeight?: number;
                fillColor?: string;
                fillOpacity?: number;
            }
            
            interface Polygon {
                setMap(map: Map | null): void;
                setPaths(paths: LatLng[] | LatLngLiteral[]): void;
            }
            
            interface Icon {
                url: string;
                scaledSize?: Size;
                anchor?: Point;
            }
            
            interface Symbol {
                path: string;
                fillColor?: string;
                fillOpacity?: number;
                strokeColor?: string;
                strokeOpacity?: number;
                strokeWeight?: number;
                scale?: number;
            }
            
            interface Size {
                width: number;
                height: number;
            }
            
            interface Point {
                x: number;
                y: number;
            }
            
            interface LatLng {
                lat(): number;
                lng(): number;
            }
            
            interface LatLngLiteral {
                lat: number;
                lng: number;
            }
            
            interface LatLngBounds {
                extend(point: LatLng | LatLngLiteral): void;
            }
        }
    }
}
