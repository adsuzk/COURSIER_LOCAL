package com.suzosky.coursierclient.net

import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.DatabaseReference
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow

/**
 * Minimal wrapper over Firebase Realtime Database for live tracking.
 * Structure (suggested):
 *   couriers/{courierId}/location -> { lat: Double, lng: Double, ts: Long }
 *   orders/{orderId}/status      -> { state: String, courierId: String?, ts: Long }
 */
object RealtimeManager {
    private val db: FirebaseDatabase by lazy {
        FirebaseDatabase.getInstance().apply {
            setPersistenceEnabled(false)
        }
    }

    private fun ref(path: String): DatabaseReference = db.getReference(path)

    data class LocationUpdate(val lat: Double, val lng: Double, val ts: Long)
    data class OrderStatus(val state: String, val courierId: String?, val ts: Long)

    // Write APIs
    fun updateCourierLocation(courierId: String, lat: Double, lng: Double, ts: Long = System.currentTimeMillis()) {
        val data = mapOf(
            "lat" to lat,
            "lng" to lng,
            "ts" to ts
        )
        ref("couriers/$courierId/location").setValue(data)
    }

    fun updateOrderStatus(orderId: String, state: String, courierId: String? = null, ts: Long = System.currentTimeMillis()) {
        val data = mapOf(
            "state" to state,
            "courierId" to courierId,
            "ts" to ts
        )
        ref("orders/$orderId/status").setValue(data)
    }

    // Read/Subscribe APIs
    fun observeCourierLocation(courierId: String): Flow<LocationUpdate?> = callbackFlow {
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                val lat = snapshot.child("lat").getValue(Double::class.java)
                val lng = snapshot.child("lng").getValue(Double::class.java)
                val ts = snapshot.child("ts").getValue(Long::class.java)
                if (lat != null && lng != null && ts != null) {
                    trySend(LocationUpdate(lat, lng, ts)).isSuccess
                } else {
                    trySend(null).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                // Emit null to signal interruption; UI can show a toast
                trySend(null).isSuccess
            }
        }
        val r = ref("couriers/$courierId/location")
        r.addValueEventListener(listener)
        awaitClose { r.removeEventListener(listener) }
    }

    fun observeOrderStatus(orderId: String): Flow<OrderStatus?> = callbackFlow {
        val listener = object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                val state = snapshot.child("state").getValue(String::class.java)
                val courierId = snapshot.child("courierId").getValue(String::class.java)
                val ts = snapshot.child("ts").getValue(Long::class.java)
                if (state != null && ts != null) {
                    trySend(OrderStatus(state, courierId, ts)).isSuccess
                } else {
                    trySend(null).isSuccess
                }
            }

            override fun onCancelled(error: DatabaseError) {
                trySend(null).isSuccess
            }
        }
        val r = ref("orders/$orderId/status")
        r.addValueEventListener(listener)
        awaitClose { r.removeEventListener(listener) }
    }
}
